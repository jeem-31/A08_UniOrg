<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentProcessorService
{
    private $ocrService;
    
    // Document types based on accreditation requirements
    private $documentTypes = [
        'letter_of_application' => [
            'keywords' => ['letter of application', 'application for', 'organization recognition', 'applying for', 'request for recognition'],
            'name' => 'Letter of Application'
        ],
        'constitution' => [
            'keywords' => ['constitution', 'by-laws', 'bylaws', 'article i', 'article ii', 'preamble'],
            'name' => 'Constitution and By-Laws'
        ],
        'plan_of_activities' => [
            'keywords' => ['plan of activities', 'activity plan', 'proposed activities', 'calendar of activities', 'annual plan'],
            'name' => 'Plan of Activities'
        ],
        'accomplishment_report' => [
            'keywords' => ['accomplishment report', 'accomplishments', 'activities conducted', 'completed activities', 'annual report'],
            'name' => 'Accomplishment Report'
        ],
        'certification_of_officers' => [
            'keywords' => ['certification of officers', 'list of officers', 'elected officers', 'organizational structure', 'officers and members', 'president', 'vice president', 'secretary', 'treasurer', 'auditor'],
            'name' => 'Certification of Officers'
        ],
        'adviser_commitment' => [
            'keywords' => ['adviser commitment', 'commitment form', 'adviser\'s commitment', 'faculty adviser', 'senior adviser', 'junior adviser'],
            'name' => 'Adviser Commitment Form'
        ],
        'dean_certification' => [
            'keywords' => ['certification', 'dean', 'associate dean', 'certify', 'certified'],
            'name' => 'Certification from Dean'
        ],
        'financial_report' => [
            'keywords' => ['financial report', 'financial statement', 'budget', 'income', 'expenses', 'treasurer report'],
            'name' => 'Financial Report'
        ],
        'accreditation_form' => [
            'keywords' => ['accreditation', 'ched memo', 'recognition', 'renewal form', 'organization renewal'],
            'name' => 'Accreditation Form'
        ]
    ];
    
    public function __construct(OcrService $ocrService)
    {
        $this->ocrService = $ocrService;
    }
    
    /**
     * Process a document: OCR + NLP analysis
     */
    public function processDocument($filePath, $originalFileName = null)
    {
        $startTime = microtime(true);
        Log::info('🔵 [RULE-BASED] Starting document processing', [
            'file' => $originalFileName ?? basename($filePath),
            'path' => $filePath
        ]);
        
        try {
            // Step 1: Extract text using OCR
            Log::info('🔵 [RULE-BASED] Step 1: OCR text extraction');
            $extractedText = $this->ocrService->basahinAngPicture($filePath);
            Log::info('🔵 [RULE-BASED] OCR completed', [
                'text_length' => strlen($extractedText),
                'preview' => substr($extractedText, 0, 100) . '...'
            ]);
            
            if (empty(trim($extractedText))) {
                throw new \Exception('No text could be extracted from the document');
            }
            
            // Step 2: Detect document type
            Log::info('🔵 [RULE-BASED] Step 2: Detecting document type');
            $documentType = $this->detectDocumentType($extractedText);
            Log::info('🔵 [RULE-BASED] Document type detected', [
                'type' => $documentType,
                'type_name' => $this->documentTypes[$documentType]['name'] ?? 'Unknown'
            ]);
            
            // Step 3: Extract key information
            Log::info('🔵 [RULE-BASED] Step 3: Extracting key information');
            $keyInfo = $this->extractKeyInformation($extractedText, $documentType);
            Log::info('🔵 [RULE-BASED] Key information extracted', [
                'org_name' => $keyInfo['organization_name'] ?? 'Not found',
                'school_year' => $keyInfo['school_year'] ?? 'Not found',
                'dates_count' => count($keyInfo['dates'] ?? [])
            ]);
            
            // Step 4: Generate summary
            Log::info('🔵 [RULE-BASED] Step 4: Generating summary');
            $summary = $this->generateSummary($extractedText, $documentType, $keyInfo);
            Log::info('🔵 [RULE-BASED] Summary generated', [
                'summary_length' => strlen($summary)
            ]);
            
            // Step 5: Generate tags
            Log::info('🔵 [RULE-BASED] Step 5: Generating tags');
            $tags = $this->generateTags($extractedText, $documentType, $keyInfo);
            Log::info('🔵 [RULE-BASED] Tags generated', ['tags' => $tags]);
            
            $confidence = $this->calculateConfidence($extractedText, $documentType);
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::info('✅ [RULE-BASED] Processing completed successfully', [
                'confidence' => $confidence,
                'processing_time_ms' => $processingTime
            ]);
            
            return [
                'success' => true,
                'extracted_text' => $extractedText,
                'document_type' => $documentType,
                'document_type_name' => $this->documentTypes[$documentType]['name'] ?? 'Unknown Document',
                'key_information' => $keyInfo,
                'summary' => $summary,
                'tags' => $tags,
                'confidence_score' => $confidence,
                'processing_time_ms' => $processingTime,
                'processed_at' => now()->toDateTimeString()
            ];
            
        } catch (\Exception $e) {
            Log::error('❌ [RULE-BASED] Processing failed', [
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
     * Detect document type using NLP keyword matching
     */
    private function detectDocumentType($text)
    {
        $text = strtolower($text);
        $scores = [];
        
        foreach ($this->documentTypes as $type => $config) {
            $score = 0;
            foreach ($config['keywords'] as $keyword) {
                // Count occurrences of keyword
                $count = substr_count($text, strtolower($keyword));
                $score += $count * 10; // Weight each occurrence
                
                // Bonus if keyword appears in first 500 characters (likely title/header)
                if (stripos(substr($text, 0, 500), $keyword) !== false) {
                    $score += 20;
                }
            }
            $scores[$type] = $score;
        }
        
        // Return type with highest score
        arsort($scores);
        $topType = array_key_first($scores);
        
        // If no clear match, return unknown
        return $scores[$topType] > 0 ? $topType : 'unknown';
    }
    
    /**
     * Extract key information from document
     */
    private function extractKeyInformation($text, $documentType)
    {
        $info = [];
        
        // Extract organization name
        $info['organization_name'] = $this->extractOrganizationName($text);
        
        // Extract dates
        $info['dates'] = $this->extractDates($text);
        
        // Extract school year
        $info['school_year'] = $this->extractSchoolYear($text);
        
        // Type-specific extraction
        switch ($documentType) {
            case 'financial_report':
                $info['budget_info'] = $this->extractBudgetInfo($text);
                break;
            case 'plan_of_activities':
                $info['activity_count'] = $this->countActivities($text);
                break;
            case 'adviser_commitment':
                $info['adviser_names'] = $this->extractAdviserNames($text);
                break;
        }
        
        return $info;
    }
    
    /**
     * Extract organization name from text
     */
    private function extractOrganizationName($text)
    {
        // Common patterns for organization names
        $patterns = [
            '/organization\s*name\s*:\s*([^\n]+)/i',
            '/organization\s*:\s*([^\n]+)/i',
            '/name\s*of\s*organization\s*:\s*([^\n]+)/i',
            '/([A-Z][A-Za-z\s&]+(?:Organization|Society|Club|Association|Council))/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return null;
    }
    
    /**
     * Extract dates from text
     */
    private function extractDates($text)
    {
        $dates = [];
        
        // Match various date formats
        $patterns = [
            '/\b\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}\b/',
            '/\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4}\b/i',
            '/\b\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4}\b/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                $dates = array_merge($dates, $matches[0]);
            }
        }
        
        return array_unique($dates);
    }
    
    /**
     * Extract school year
     */
    private function extractSchoolYear($text)
    {
        if (preg_match('/(?:school year|s\.?y\.?|academic year|a\.?y\.?)\s*:?\s*(\d{4}[-–]\d{4})/i', $text, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/\b(\d{4}[-–]\d{4})\b/', $text, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Extract budget information
     */
    private function extractBudgetInfo($text)
    {
        $amounts = [];
        
        // Match currency amounts
        if (preg_match_all('/(?:₱|PHP|Php)\s*[\d,]+(?:\.\d{2})?/', $text, $matches)) {
            $amounts = $matches[0];
        }
        
        return [
            'amounts_found' => count($amounts),
            'sample_amounts' => array_slice($amounts, 0, 5)
        ];
    }
    
    /**
     * Count activities in plan
     */
    private function countActivities($text)
    {
        // Look for numbered lists or activity mentions
        $count = 0;
        
        // Count numbered items (1., 2., etc.)
        preg_match_all('/^\s*\d+\.\s+/m', $text, $matches);
        $count = count($matches[0]);
        
        return $count;
    }
    
    /**
     * Extract adviser names
     */
    private function extractAdviserNames($text)
    {
        $names = [];
        
        // Look for name patterns after "adviser" keyword
        if (preg_match_all('/(?:adviser|advisor)\s*:?\s*([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)/i', $text, $matches)) {
            $names = $matches[1];
        }
        
        return $names;
    }
    
    /**
     * Generate summary based on document type
     */
    private function generateSummary($text, $documentType, $keyInfo)
    {
        $orgName = $keyInfo['organization_name'] ?? 'the organization';
        $schoolYear = $keyInfo['school_year'] ?? 'the current academic year';
        
        $summaries = [
            'letter_of_application' => "This is a Letter of Application from {$orgName} requesting recognition or renewal for {$schoolYear}. The document outlines the organization's purpose and objectives for seeking official recognition from OSAS.",
            
            'constitution' => "This Constitution and By-Laws document from {$orgName} establishes the organization's structure, governance, and operational guidelines. It defines the organization's mission, membership requirements, and officer responsibilities.",
            
            'plan_of_activities' => "This Plan of Activities from {$orgName} outlines the proposed events and programs for {$schoolYear}. " . 
                (isset($keyInfo['activity_count']) && $keyInfo['activity_count'] > 0 ? "The plan includes approximately {$keyInfo['activity_count']} activities." : "The plan details the organization's scheduled activities and objectives."),
            
            'accomplishment_report' => "This Accomplishment Report from {$orgName} summarizes the activities and achievements completed during {$schoolYear}. The report documents the organization's contributions and impact on the student community.",
            
            'certification_of_officers' => "This Certification of Officers from {$orgName} lists the elected officers and their respective positions for {$schoolYear}. The document includes officer names, positions, and contact information, serving as an official record of the organization's leadership structure.",
            
            'adviser_commitment' => "This Adviser Commitment Form confirms the faculty adviser's commitment to guide and supervise {$orgName}. " .
                (!empty($keyInfo['adviser_names']) ? "Adviser(s): " . implode(', ', $keyInfo['adviser_names']) : "The form includes the adviser's signature and commitment to the organization."),
            
            'dean_certification' => "This Certification from the Dean verifies and endorses {$orgName}'s application for recognition or renewal. The certification confirms the organization's compliance with college requirements.",
            
            'financial_report' => "This Financial Report from {$orgName} provides a detailed account of the organization's income and expenses for {$schoolYear}. " .
                (isset($keyInfo['budget_info']['amounts_found']) && $keyInfo['budget_info']['amounts_found'] > 0 ? "The report includes {$keyInfo['budget_info']['amounts_found']} financial entries." : "The report documents the organization's financial activities."),
            
            'accreditation_form' => "This Accreditation Form from {$orgName} contains the official requirements for organization recognition and accreditation for {$schoolYear}. The form follows CHED Memo Order guidelines for student organization registration.",
        ];
        
        return $summaries[$documentType] ?? "This document from {$orgName} is related to the organization's accreditation requirements for {$schoolYear}. The document contains important information for OSAS review and processing.";
    }
    
    /**
     * Generate tags for document
     */
    private function generateTags($text, $documentType, $keyInfo)
    {
        $tags = [];
        
        // Add document type tag
        $tags[] = $this->documentTypes[$documentType]['name'] ?? 'Unknown';
        
        // Add organization name tag
        if (!empty($keyInfo['organization_name'])) {
            $tags[] = $keyInfo['organization_name'];
        }
        
        // Add school year tag
        if (!empty($keyInfo['school_year'])) {
            $tags[] = $keyInfo['school_year'];
        }
        
        // Add content-based tags
        $text = strtolower($text);
        
        if (stripos($text, 'renewal') !== false) {
            $tags[] = 'Renewal';
        }
        if (stripos($text, 'new organization') !== false || stripos($text, 'new application') !== false) {
            $tags[] = 'New Organization';
        }
        if (stripos($text, 'ched memo') !== false) {
            $tags[] = 'CHED Compliance';
        }
        if (stripos($text, 'accreditation') !== false) {
            $tags[] = 'Accreditation';
        }
        
        return array_unique($tags);
    }
    
    /**
     * Calculate confidence score for document type detection
     */
    private function calculateConfidence($text, $documentType)
    {
        if ($documentType === 'unknown') {
            return 0;
        }
        
        $text = strtolower($text);
        $matchCount = 0;
        $keywords = $this->documentTypes[$documentType]['keywords'];
        
        foreach ($keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $matchCount++;
            }
        }
        
        // Calculate percentage
        $confidence = ($matchCount / count($keywords)) * 100;
        
        return min(100, $confidence);
    }
}
