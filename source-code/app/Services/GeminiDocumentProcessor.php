<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GeminiDocumentProcessor
{
    private $ocrService;
    private $groq;
    
    public function __construct(OcrService $ocrService, GroqService $groq)
    {
        $this->ocrService = $ocrService;
        $this->groq = $groq;
    }
    
    /**
     * Process document using Google Gemini AI
     */
    public function processDocument($filePath, $originalFileName = null)
    {
        $startTime = microtime(true);
        Log::info('🟣 [GEMINI] Starting AI document processing', [
            'file' => $originalFileName ?? basename($filePath),
            'path' => $filePath
        ]);
        
        try {
            // Step 1: Quick OCR of first page to detect document type
            Log::info('🟣 [GEMINI] Step 1: Quick document type detection (first page only)');
            $firstPageText = $this->ocrService->basahinAngPicture($filePath, 1);
            
            // Step 2: Use Gemini to classify document type
            Log::info('🟣 [GEMINI] Step 2: Classifying document type with Gemini');
            $documentType = $this->groq->classifyDocument($firstPageText);
            Log::info('🟣 [GEMINI] Document type detected', [
                'type' => $documentType
            ]);
            
            // Step 3: Determine how many pages to process based on document type
            $maxPages = $this->getMaxPagesForDocumentType($documentType);
            Log::info('🟣 [GEMINI] Step 3: Processing document', [
                'max_pages' => $maxPages,
                'reason' => $maxPages > 1 ? 'Plan of Activities needs full document' : 'Other documents need first page only'
            ]);
            
            // Step 4: Extract full text based on page limit
            $extractedText = $maxPages > 1 
                ? $this->ocrService->basahinAngPicture($filePath, $maxPages)
                : $firstPageText;
                
            Log::info('🟣 [GEMINI] OCR completed', [
                'text_length' => strlen($extractedText),
                'pages_processed' => $maxPages,
                'preview' => substr($extractedText, 0, 100) . '...'
            ]);
            
            if (empty(trim($extractedText))) {
                throw new \Exception('No text could be extracted from the document');
            }
            
            // Step 5: Use Gemini to analyze the entire document
            Log::info('🟣 [GEMINI] Step 4: Analyzing document with Gemini AI');
            $analysis = $this->groq->summarizeDocument($extractedText, $documentType);
            Log::info('🟣 [GEMINI] Analysis completed', [
                'has_summary' => isset($analysis['summary']),
                'has_org_name' => isset($analysis['organization_name']),
                'document_type' => $analysis['document_type'] ?? 'Unknown'
            ]);
            
            // Step 6: Build structured response
            $keyInfo = [
                'organization_name' => $analysis['organization_name'] ?? null,
                'school_year' => $analysis['school_year'] ?? null,
                'dates' => $analysis['key_dates'] ?? [],
                'additional_info' => $analysis['key_information'] ?? null,
                'pages_processed' => $maxPages
            ];
            
            // Step 7: Generate tags
            $tags = $this->generateTags($analysis);
            
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::info('✅ [GEMINI] Processing completed successfully', [
                'processing_time_ms' => $processingTime,
                'pages_processed' => $maxPages
            ]);
            
            return [
                'success' => true,
                'method' => 'gemini',
                'extracted_text' => $extractedText,
                'document_type' => $this->normalizeDocumentType($analysis['document_type'] ?? 'unknown'),
                'document_type_name' => $analysis['document_type'] ?? 'Unknown Document',
                'key_information' => $keyInfo,
                'summary' => $analysis['summary'] ?? 'No summary available',
                'tags' => $tags,
                'confidence_score' => 95, // Gemini is highly accurate
                'processing_time_ms' => $processingTime,
                'processed_at' => now()->toDateTimeString(),
                'raw_analysis' => $analysis
            ];
            
        } catch (\Exception $e) {
            Log::error('❌ [GEMINI] Processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Determine maximum pages to process based on document type
     */
    private function getMaxPagesForDocumentType($documentType)
    {
        $documentType = strtolower($documentType);
        
        // Plan of Activities needs all pages (up to 20 pages max for safety)
        if (stripos($documentType, 'plan of activities') !== false ||
            stripos($documentType, 'program of activities') !== false ||
            stripos($documentType, 'activity plan') !== false) {
            return 20; // Process up to 20 pages for Plan of Activities
        }
        
        // All other documents: first page only
        return 1;
    }
    
    /**
     * Normalize document type to match system types
     */
    private function normalizeDocumentType($type)
    {
        $type = strtolower($type);
        
        $mapping = [
            'letter of application' => 'letter_of_application',
            'application letter' => 'letter_of_application',
            'constitution' => 'constitution',
            'constitution and by-laws' => 'constitution',
            'constitution and bylaws' => 'constitution',
            'bylaws' => 'constitution',
            'plan of activities' => 'plan_of_activities',
            'activity plan' => 'plan_of_activities',
            'program of activities' => 'plan_of_activities',
            'accomplishment report' => 'accomplishment_report',
            'certification of officers' => 'certification_of_officers',
            'list of officers' => 'certification_of_officers',
            'officers list' => 'certification_of_officers',
            'list of officers members' => 'certification_of_officers',
            'adviser commitment' => 'adviser_commitment',
            'adviser commitment form' => 'adviser_commitment',
            'dean certification' => 'dean_certification',
            'certification from dean' => 'dean_certification',
            'financial report' => 'financial_report',
            'accreditation form' => 'accreditation_form',
            'renewal form' => 'accreditation_form',
        ];
        
        foreach ($mapping as $key => $value) {
            if (stripos($type, $key) !== false) {
                return $value;
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Generate tags from analysis
     */
    private function generateTags($analysis)
    {
        $tags = [];
        
        if (!empty($analysis['document_type'])) {
            $tags[] = $analysis['document_type'];
        }
        
        if (!empty($analysis['organization_name'])) {
            $tags[] = $analysis['organization_name'];
        }
        
        if (!empty($analysis['school_year'])) {
            $tags[] = $analysis['school_year'];
        }
        
        // Add additional tags from key information
        if (!empty($analysis['key_information'])) {
            $info = is_string($analysis['key_information']) 
                ? $analysis['key_information'] 
                : json_encode($analysis['key_information']);
            
            if (stripos($info, 'renewal') !== false) {
                $tags[] = 'Renewal';
            }
            if (stripos($info, 'new') !== false) {
                $tags[] = 'New Organization';
            }
            if (stripos($info, 'accreditation') !== false) {
                $tags[] = 'Accreditation';
            }
        }
        
        return array_unique(array_filter($tags));
    }
}
