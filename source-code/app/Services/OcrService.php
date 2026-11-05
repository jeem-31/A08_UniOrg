<?php

namespace App\Services;

use thiagoalessio\TesseractOCR\TesseractOCR;
use Smalot\PdfParser\Parser as PdfParser;

class OcrService
{
    /**
     * Images larger than this (in the longest side) are downscaled and
     * converted to grayscale before OCR so Tesseract finishes faster.
     * 2000px provides optimal ~200-240 DPI for documents ensuring high OCR accuracy and fast performance.
     */
    private const MAX_OCR_DIMENSION = 2000;

    public function basahinAngPicture($filePath, $maxPages = 1, $pageOffset = 0)
    {
        try {
            $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $extractedText = '';
            
            // Handle text files directly (for testing or pre-extracted text)
            if (in_array($fileExtension, ['txt', 'text'])) {
                return file_get_contents($filePath);
            }
            
            // Handle DOCX files
            if ($fileExtension === 'docx') {
                return $this->extractDocxText($filePath);
            }
            
            if ($fileExtension === 'pdf') {
                $extractedText = $this->extractNativePdfText($filePath, $maxPages, $pageOffset);
                
                if (strlen(trim($extractedText)) < 50) {
                    $imagePaths = $this->convertPdfToImage($filePath, $maxPages, $pageOffset);
                    
                    // Process each page
                    foreach ($imagePaths as $imagePath) {
                        $extractedText .= $this->performOcr($imagePath) . "\n\n--- PAGE BREAK ---\n\n";
                        
                        if (file_exists($imagePath)) {
                            unlink($imagePath);
                        }
                    }
                }
            } else {
                $extractedText = $this->performOcr($filePath);
            }
            
            return $extractedText;
        } catch (\Exception $e) {
            throw new \Exception('Hindi mabasa ang file: ' . $e->getMessage());
        }
    }

    /**
     * Force visual page rendering to image + OCR for PDFs to bypass stale hidden background text streams
     */
    public function basahinAngPictureVisual($filePath, $maxPages = 16)
    {
        $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($fileExtension === 'pdf') {
            try {
                $imagePaths = $this->convertPdfToImage($filePath, $maxPages ?: 16);
                $extractedText = '';
                foreach ($imagePaths as $imagePath) {
                    $extractedText .= $this->performOcr($imagePath) . "\n\n--- PAGE BREAK ---\n\n";
                    if (file_exists($imagePath)) {
                        @unlink($imagePath);
                    }
                }
                return $extractedText;
            } catch (\Throwable $e) {
                // Fallback to regular OCR method if image rendering fails
            }
        }
        return $this->basahinAngPicture($filePath, $maxPages);
    }
    
    private function extractNativePdfText($pdfPath, $maxPages = 1, $pageOffset = 0)
    {
        // 1. Ultra-fast C++ pdftotext (Poppler binary) if available on Linux server (takes < 10ms)
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            try {
                $output = [];
                $returnCode = -1;
                $firstArg = $pageOffset + 1;
                $limitArg = $maxPages > 0 ? '-l '.($pageOffset + $maxPages) : '';
                exec("pdftotext -f {$firstArg} {$limitArg} \"{$pdfPath}\" - 2>/dev/null", $output, $returnCode);
                if ($returnCode === 0 && !empty($output)) {
                    $fastText = implode("\n", $output);
                    if (strlen(trim($fastText)) >= 20) {
                        return $fastText;
                    }
                }
            } catch (\Throwable $e) {
                // Continue to PHP parser fallback
            }
        }

        // 2. Pure PHP PdfParser library fallback
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($pdfPath);
            
            // Get all pages
            $pages = $pdf->getPages();
            $text = '';
            $pageCount = 0;
            $skipped = 0;
            
            foreach ($pages as $page) {
                if ($skipped < $pageOffset) {
                    $skipped++;
                    continue;
                }
                if ($pageCount >= $maxPages && $maxPages > 0) {
                    break;
                }
                $text .= $page->getText() . "\n\n--- PAGE BREAK ---\n\n";
                $pageCount++;
            }
            
            return $text;
        } catch (\Throwable $e) {
            return '';
        }
    }
    
    private function extractDocxText($docxPath)
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($docxPath) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                
                if ($xml) {
                    // Remove XML tags and extract text
                    $text = strip_tags($xml);
                    return $text;
                }
            }
            return '';
        } catch (\Exception $e) {
            return '';
        }
    }
    
    /**
     * Downscale and grayscale large images before OCR so Tesseract finishes
     * faster on phone photos and scans. Small images are returned untouched.
     */
    public function preprocessImage($imagePath)
    {
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return $imagePath;
        }

        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng')) {
            return $imagePath;
        }

        if (!file_exists($imagePath)) {
            return $imagePath;
        }

        $size = @getimagesize($imagePath);
        if (!$size) {
            return $imagePath;
        }

        $width = $size[0];
        $height = $size[1];

        if ($width <= self::MAX_OCR_DIMENSION && $height <= self::MAX_OCR_DIMENSION) {
            return $imagePath;
        }

        $src = $ext === 'png' ? @imagecreatefrompng($imagePath) : @imagecreatefromjpeg($imagePath);
        if (!$src) {
            return $imagePath;
        }

        $scale = self::MAX_OCR_DIMENSION / max($width, $height);
        $newWidth = (int) max(1, round($width * $scale));
        $newHeight = (int) max(1, round($height * $scale));

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagefilter($dst, IMG_FILTER_GRAYSCALE);

        // Fast JPEG encoding avoids the high CPU overhead of PNG compression level 6
        $tempPath = $imagePath . '.ocr-prep.jpg';
        if (function_exists('imagejpeg')) {
            imagejpeg($dst, $tempPath, 90);
        } else {
            $tempPath = $imagePath . '.ocr-prep.png';
            imagepng($dst, $tempPath, 1);
        }

        imagedestroy($src);
        imagedestroy($dst);

        return $tempPath;
    }

    private function performOcr($imagePath)
    {
        $ocrSourcePath = $imagePath;

        try {
            $ocrSourcePath = $this->preprocessImage($imagePath);

            $ocr = new TesseractOCR($ocrSourcePath);
            
            // Limit OpenMP threads to 1 to prevent CPU contention on shared/low-core cloud instances (Render)
            putenv('OMP_THREAD_LIMIT=1');

            // Check if running on Windows (local) or Linux (server)
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows (local development)
                $tesseractPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
                if (file_exists($tesseractPath)) {
                    $ocr->executable($tesseractPath);
                }
                putenv('TESSDATA_PREFIX=C:\\Program Files\\Tesseract-OCR\\tessdata');
            } else {
                // Linux (deployed server - Railway, Render, etc.)
                $linuxCandidates = [
                    '/usr/bin/tesseract',
                    '/usr/local/bin/tesseract',
                    '/bin/tesseract',
                    '/nix/var/nix/profiles/default/bin/tesseract',
                ];
                foreach ($linuxCandidates as $candidate) {
                    if (file_exists($candidate)) {
                        $ocr->executable($candidate);
                        break;
                    }
                }

                $tessdataCandidates = [
                    '/usr/share/tesseract-ocr/5/tessdata',
                    '/usr/share/tesseract-ocr/4.00/tessdata',
                    '/usr/share/tessdata',
                    '/usr/local/share/tessdata',
                ];
                foreach ($tessdataCandidates as $prefix) {
                    if (is_dir($prefix)) {
                        putenv('TESSDATA_PREFIX=' . $prefix);
                        break;
                    }
                }
            }
            
            $ocr->psm(3);
            $text = $ocr->run();
            
            return $text;
        } catch (\Exception $e) {
            throw new \Exception('OCR failed: ' . $e->getMessage());
        } finally {
            if ($ocrSourcePath !== $imagePath && file_exists($ocrSourcePath)) {
                @unlink($ocrSourcePath);
            }
        }
    }
    
    private function convertPdfToImage($pdfPath, $maxPages = 1, $pageOffset = 0)
    {
        try {
            // Normalize paths
            $pdfPath = str_replace('/', DIRECTORY_SEPARATOR, $pdfPath);
            $imagePaths = [];

            // Ensure PDF exists
            if (!file_exists($pdfPath)) {
                throw new \Exception('PDF file not found: ' . $pdfPath);
            }

            // Ensure the output directory exists (Laravel only auto-creates
            // storage/app/temp on upload, not this private temp folder).
            $tempDir = storage_path('app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'temp');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0775, true);
            }

            // Resolve Ghostscript path (Windows local vs Linux server)
            $gsPath = null;
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                foreach ([
                    'C:\\Program Files\\gs\\gs10.07.0\\bin\\gswin64c.exe',
                    'C:\\Program Files\\gs\\gswin64c.exe',
                ] as $candidate) {
                    if (file_exists($candidate)) {
                        $gsPath = $candidate;
                        break;
                    }
                }
            } else {
                $gsPath = 'gs';
            }

            // Convert pages (150 DPI JPEG is optimal for OCR speed and accuracy).
            // $pageOffset shifts the rendered window for paginated extraction.
            for ($page = $pageOffset + 1; $page <= $pageOffset + $maxPages; $page++) {
                $tempImagePath = storage_path('app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . uniqid() . '_page' . $page . '.jpg');
                $converted = false;

                // 1) Imagick PHP extension — fast JPEG rendering
                if (! $converted && class_exists(\Imagick::class)) {
                    try {
                        $imagick = new \Imagick();
                        $imagick->setResolution(150, 150);
                        $imagick->readImage($pdfPath . '[' . ($page - 1) . ']');
                        $imagick->setImageFormat('jpeg');
                        $imagick->setImageCompressionQuality(90);
                        file_put_contents($tempImagePath, $imagick->getImageBlob());
                        $imagick->clear();
                        if (file_exists($tempImagePath) && filesize($tempImagePath) > 0) {
                            $converted = true;
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('PDF convert: Imagick tier failed', [
                            'error' => $e->getMessage(),
                            'page' => $page,
                        ]);
                        @unlink($tempImagePath);
                    }
                }

                // 2) Ghostscript command (Fast JPEG output at 150 DPI with anti-aliasing)
                if (! $converted && $gsPath !== null) {
                    $command = "\"{$gsPath}\" -dSAFER -dNOPAUSE -dBATCH -sDEVICE=jpeg -dJPEGQ=90 -r150 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -dFirstPage={$page} -dLastPage={$page} -sOutputFile=\"{$tempImagePath}\" \"{$pdfPath}\" 2>&1";
                    $output = [];
                    $returnCode = -1;
                    exec($command, $output, $returnCode);
                    if ($returnCode === 0 && file_exists($tempImagePath) && filesize($tempImagePath) > 0) {
                        $converted = true;
                    } else {
                        \Illuminate\Support\Facades\Log::warning('PDF convert: Ghostscript tier failed', [
                            'return_code' => $returnCode,
                            'output' => implode("\n", $output),
                            'page' => $page,
                        ]);
                    }
                }

                // 3) ImageMagick convert fallback (150 DPI JPEG)
                if (! $converted) {
                    $convertOutput = [];
                    $convertReturnCode = -1;
                    $convertCommand = "convert -density 150 -quality 90 \"{$pdfPath}\"[" . ($page - 1) . "] \"{$tempImagePath}\" 2>&1";
                    exec($convertCommand, $convertOutput, $convertReturnCode);
                    if ($convertReturnCode === 0 && file_exists($tempImagePath) && filesize($tempImagePath) > 0) {
                        $converted = true;
                    } else {
                        \Illuminate\Support\Facades\Log::warning('PDF convert: ImageMagick tier failed', [
                            'return_code' => $convertReturnCode,
                            'output' => implode("\n", $convertOutput),
                            'page' => $page,
                        ]);
                    }
                }

                if (! $converted) {
                    @unlink($tempImagePath);
                    // If this page fails, stop trying more pages
                    break;
                }

                $imagePaths[] = $tempImagePath;
            }

            if (empty($imagePaths)) {
                throw new \Exception('Failed to convert PDF pages');
            }

            return $imagePaths;
        } catch (\Exception $e) {
            throw new \Exception('Hindi ma-convert ang PDF: ' . $e->getMessage());
        }
    }
}
