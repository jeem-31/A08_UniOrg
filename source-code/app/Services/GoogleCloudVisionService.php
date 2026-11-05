<?php

namespace App\Services;

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\Feature\Type;
use Illuminate\Support\Facades\Log;

class GoogleCloudVisionService
{
    protected $client;
    protected $enabled;

    public function __construct()
    {
        $this->enabled = env('GOOGLE_CLOUD_VISION_ENABLED', false);
        
        if ($this->enabled) {
            try {
                // Set credentials path (absolute path)
                $keyFilePath = storage_path('app/google-cloud/credentials.json');
                
                if (!file_exists($keyFilePath)) {
                    Log::error('Google Cloud credentials file not found: ' . $keyFilePath);
                    $this->enabled = false;
                    return;
                }
                
                // Set environment variable
                putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $keyFilePath);
                
                // Initialize client with explicit credentials
                // Create Guzzle client with SSL verification disabled (Windows development fix)
                $guzzleClient = new \GuzzleHttp\Client([
                    'verify' => false
                ]);
                
                $this->client = new ImageAnnotatorClient([
                    'credentials' => json_decode(file_get_contents($keyFilePath), true),
                    'transport' => 'rest',
                    'transportConfig' => [
                        'rest' => [
                            'httpHandler' => function ($request, $options = []) use ($guzzleClient) {
                                return $guzzleClient->sendAsync($request, $options);
                            }
                        ]
                    ]
                ]);
                
                Log::info('✅ Google Cloud Vision initialized successfully');
            } catch (\Exception $e) {
                Log::error('❌ Failed to initialize Google Cloud Vision: ' . $e->getMessage());
                $this->enabled = false;
            }
        }
    }

    /**
     * Check if Google Cloud Vision is enabled and available
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->client !== null;
    }

    /**
     * Extract text from image using Google Cloud Vision OCR
     * 
     * @param string $imagePath Path to image file
     * @return array ['success' => bool, 'text' => string, 'confidence' => float]
     */
    public function extractText(string $imagePath): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'text' => '',
                'confidence' => 0,
                'error' => 'Google Cloud Vision is not enabled'
            ];
        }

        try {
            Log::info('🔍 [Google Cloud Vision] Starting OCR for: ' . basename($imagePath));

            // Read image file
            $imageContent = file_get_contents($imagePath);
            
            // Create image object
            $image = new Image();
            $image->setContent($imageContent);

            // Create request object
            $request = (new \Google\Cloud\Vision\V1\AnnotateImageRequest())
                ->setImage($image)
                ->setFeatures([
                    (new \Google\Cloud\Vision\V1\Feature())
                        ->setType(\Google\Cloud\Vision\V1\Feature\Type::DOCUMENT_TEXT_DETECTION)
                ]);

            // Create batch request
            $batchRequest = (new \Google\Cloud\Vision\V1\BatchAnnotateImagesRequest())
                ->setRequests([$request]);

            // Perform document text detection
            $response = $this->client->batchAnnotateImages($batchRequest);
            $responses = $response->getResponses();

            if (count($responses) > 0) {
                $annotation = $responses[0]->getFullTextAnnotation();
                
                if ($annotation) {
                    $text = $annotation->getText();
                    $pages = $annotation->getPages();
                    // Google Cloud returns confidence as 0.0-1.0, convert to percentage (0-100)
                    $confidenceRaw = ($pages && count($pages) > 0) ? $pages[0]->getConfidence() : 0.95;
                    $confidence = round($confidenceRaw * 100, 2); // Convert to percentage

                    // Track usage (1 unit per image)
                    \App\Services\GoogleCloudUsageTracker::trackVisionUsage(1);

                    Log::info('✅ [Google Cloud Vision] OCR successful', [
                        'text_length' => strlen($text),
                        'confidence' => $confidence . '%'
                    ]);

                    return [
                        'success' => true,
                        'text' => $text,
                        'confidence' => $confidence,
                        'pages' => count($pages)
                    ];
                }
            }

            return [
                'success' => false,
                'text' => '',
                'confidence' => 0,
                'error' => 'No text detected in image'
            ];

        } catch (\Exception $e) {
            Log::error('❌ [Google Cloud Vision] OCR failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'text' => '',
                'confidence' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract text from PDF (converts first page to image)
     * 
     * @param string $pdfPath Path to PDF file
     * @param int $maxPages Maximum pages to process
     * @return array ['success' => bool, 'text' => string, 'pages_processed' => int]
     */
    public function extractTextFromPdf(string $pdfPath, int $maxPages = 1): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'text' => '',
                'pages_processed' => 0,
                'error' => 'Google Cloud Vision is not enabled'
            ];
        }

        try {
            Log::info('📄 [Google Cloud Vision] Processing PDF: ' . basename($pdfPath));

            // For PDFs, we'll use a simpler approach: try to extract text directly
            // If that fails, we'll use pdf-to-image conversion
            
            // First, try using smalot/pdfparser to extract text
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($pdfPath);
                $text = $pdf->getText();
                
                if (!empty(trim($text))) {
                    Log::info('✅ [Google Cloud Vision] PDF text extracted directly', [
                        'text_length' => strlen($text)
                    ]);
                    
                    return [
                        'success' => true,
                        'text' => $text,
                        'pages_processed' => 1,
                        'confidence' => 90.0, // 90% confidence for direct extraction
                        'method' => 'direct_extraction'
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('⚠️ [Google Cloud Vision] Direct PDF extraction failed, trying OCR: ' . $e->getMessage());
            }

            // If direct extraction failed, try OCR on first page using spatie/pdf-to-image
            if (class_exists('\Spatie\PdfToImage\Pdf')) {
                try {
                    $pdf = new \Spatie\PdfToImage\Pdf($pdfPath);
                    
                    // Create temp image file
                    $tempImagePath = storage_path('app/temp/' . uniqid() . '.jpg');
                    
                    // Ensure temp directory exists
                    if (!file_exists(dirname($tempImagePath))) {
                        mkdir(dirname($tempImagePath), 0755, true);
                    }
                    
                    // Convert first page to image
                    $pdf->setPage(1)
                        ->setOutputFormat('jpg')
                        ->saveImage($tempImagePath);
                    
                    // Use extractText method on the image
                    $result = $this->extractText($tempImagePath);
                    
                    // Clean up temp image
                    if (file_exists($tempImagePath)) {
                        unlink($tempImagePath);
                    }
                    
                    if ($result['success']) {
                        $result['pages_processed'] = 1;
                        $result['method'] = 'pdf_to_image_ocr';
                        return $result;
                    }
                } catch (\Exception $e) {
                    Log::error('❌ [Google Cloud Vision] PDF to image conversion failed: ' . $e->getMessage());
                }
            }

            return [
                'success' => false,
                'text' => '',
                'pages_processed' => 0,
                'error' => 'No text detected in PDF. The PDF might be empty or image-based without readable text.'
            ];

        } catch (\Exception $e) {
            Log::error('❌ [Google Cloud Vision] PDF OCR failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'text' => '',
                'pages_processed' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Detect handwriting in image
     */
    public function detectHandwriting(string $imagePath): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'text' => '',
                'error' => 'Google Cloud Vision is not enabled'
            ];
        }

        try {
            $imageContent = file_get_contents($imagePath);
            $image = new Image();
            $image->setContent($imageContent);

            // Create request object
            $request = (new \Google\Cloud\Vision\V1\AnnotateImageRequest())
                ->setImage($image)
                ->setFeatures([
                    (new \Google\Cloud\Vision\V1\Feature())
                        ->setType(\Google\Cloud\Vision\V1\Feature\Type::DOCUMENT_TEXT_DETECTION)
                ]);

            // Create batch request
            $batchRequest = (new \Google\Cloud\Vision\V1\BatchAnnotateImagesRequest())
                ->setRequests([$request]);

            // Use handwriting detection
            $response = $this->client->batchAnnotateImages($batchRequest);
            $responses = $response->getResponses();

            if (count($responses) > 0) {
                $annotation = $responses[0]->getFullTextAnnotation();
                
                if ($annotation) {
                    return [
                        'success' => true,
                        'text' => $annotation->getText(),
                        'confidence' => 90.0 // 90% confidence for handwriting
                    ];
                }
            }

            return [
                'success' => false,
                'text' => '',
                'error' => 'No handwriting detected'
            ];

        } catch (\Exception $e) {
            Log::error('❌ [Google Cloud Vision] Handwriting detection failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'text' => '',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Close the client connection
     */
    public function __destruct()
    {
        if ($this->client) {
            $this->client->close();
        }
    }
}
