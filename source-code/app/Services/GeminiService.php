<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private $apiKey;
    private $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    private $model = 'gemini-flash-latest'; // Free-tier model (AQ. auth keys)

    // Gemini resolves this alias to a "thinking" model whose thought tokens
    // count against maxOutputTokens. These budgets leave enough room for the
    // actual output after thinking.
    private const MAX_DOCUMENT_TEXT_LENGTH = 30000;
    private const MAX_SUMMARY_OUTPUT_TOKENS = 8192;
    private const MAX_CLASSIFY_OUTPUT_TOKENS = 2048;

    public function __construct()
    {
        $this->apiKey = env('GOOGLE_GEMINI_API_KEY');
    }
    
    /**
     * Check if Gemini API is configured
     */
    public function isConfigured()
    {
        return !empty($this->apiKey);
    }
    
    /**
     * Generate content using one provider request. Retry accounting belongs to
     * the document-analysis orchestrator, not this provider client.
     */
    public function generate($prompt, $maxTokens = 1024, $jsonMode = false)
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Google Gemini API is not configured.');
        }

        Log::info('[GEMINI] Sending request', [
            'prompt_length' => strlen($prompt),
            'max_tokens' => $maxTokens,
            'json_mode' => $jsonMode,
        ]);

        $generationConfig = [
            'maxOutputTokens' => $maxTokens,
            'temperature' => 0.7,
        ];

        // Ask the API for a guaranteed-valid JSON payload instead of guessing
        // with brace extraction, which silently fails on truncated output.
        if ($jsonMode) {
            $generationConfig['responseMimeType'] = 'application/json';
        }

        $response = Http::timeout(120)
            ->post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [[
                    'parts' => [['text' => $prompt]],
                ]],
                'generationConfig' => $generationConfig,
            ]);

        if ($response->failed()) {
            Log::error('[GEMINI] API request failed', ['status' => $response->status()]);
            throw new \RuntimeException("Gemini API request failed with status {$response->status()}.");
        }

        $generatedText = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($generatedText) || trim($generatedText) === '') {
            throw new \UnexpectedValueException('Unexpected Gemini API response format.');
        }

        return $generatedText;
    }
    
    /**
     * Summarize document with custom instructions
     */
    public function summarizeDocument($text, $documentType = null)
    {
        $prompt = "You are a document analysis assistant for a university student organization management system (OSAS).

Analyze the following document and provide:

1. **Document Type**: Identify what type of document this is. Choose EXACTLY ONE from:
   - Letter of Application
   - Application for Recognition / Renewal Form (an application for organization recognition or renewal of accreditation of a student organization)
   - Constitution and By-Laws
   - Plan of Activities
   - Accomplishment Report
   - Certification of Officers (ONLY if document contains a LIST of officers with their POSITIONS like President, Vice President, Secretary, Treasurer, etc.)
   - List of Members (a roster or list of student members)
   - Adviser Commitment Form
   - Dean Certification (ONLY if document is a certification signed BY the Dean)
   - Financial Report
   - Other (specify)

IMPORTANT: 
- If you see officer names with positions (President, VP, Secretary, Treasurer, Auditor, etc.), it is \"Certification of Officers\"
- If you see a certification letter signed by a Dean/Associate Dean, it is \"Dean Certification\"
- An application form for organization recognition/renewal is NOT a \"Letter of Application\" — classify it as \"Application for Recognition / Renewal Form\"
- These are DIFFERENT document types!

2. **Organization Name**: Extract the name of the student organization (use Title Case, e.g., \"Junior Philippine Computer Society\")

3. **School Year**: Extract the academic year (e.g., 2024-2025)

4. **Key Dates**: List any important dates mentioned (event dates, meeting dates, effectivity dates). EXCLUDE form revision dates, form numbers, and boilerplate.

5. **Summary**: Provide a concise 2-3 sentence summary of the document's main purpose and content

6. **Key Information**: Extract any other relevant details (officers, budget amounts, activities, etc.)

7. **Tags**: Generate 1 to 10 relevant short tags, with the exact document type as the first tag.

If any field is not present in the document, use an empty string (or an empty array for lists) — never use \"Not specified\".

Format your response as JSON with these keys: document_type, organization_name, school_year, key_dates, summary, key_information, tags

Document Text:
" . substr($text, 0, self::MAX_DOCUMENT_TEXT_LENGTH) . "

Respond ONLY with valid JSON, no additional text.";

        try {
            $response = $this->generate($prompt, self::MAX_SUMMARY_OUTPUT_TOKENS, true);
            
            Log::info('🟣 [GEMINI] Raw response received', [
                'response_preview' => substr($response, 0, 200)
            ]);
            
            // Clean up response - remove markdown code blocks if present
            $response = preg_replace('/```json\s*/i', '', $response);
            $response = preg_replace('/```\s*$/i', '', $response);
            $response = trim($response);
            
            // Try to parse JSON response
            $parsed = json_decode($response, true);
            if ($parsed === null || json_last_error() !== JSON_ERROR_NONE) {
                $jsonStart = strpos($response, '{');
                $jsonEnd = strrpos($response, '}');
                
                if ($jsonStart !== false && $jsonEnd !== false) {
                    $jsonString = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
                    $parsed = json_decode($jsonString, true);
                }
            }
            if ($parsed !== null && json_last_error() === JSON_ERROR_NONE) {
                Log::info('✅ [GEMINI] JSON parsed successfully', [
                    'has_summary' => isset($parsed['summary']),
                    'has_document_type' => isset($parsed['document_type'])
                ]);
                return $parsed;
            }

            Log::warning('⚠️ [GEMINI] JSON parse error', [
                'error' => json_last_error_msg(),
                'json_preview' => substr($response, 0, 200)
            ]);

            throw new \UnexpectedValueException('Gemini returned malformed document-analysis JSON.');
        } catch (\Exception $e) {
            Log::error('❌ [GEMINI] Summarization failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Extract structured information from document
     */
    public function extractInformation($text, $fields = [])
    {
        $fieldsList = empty($fields)
            ? "organization name, school year, document type, key dates, and any other relevant information"
            : implode(', ', $fields);

        $prompt = "Extract the following information from this document: {$fieldsList}

Document:
" . substr($text, 0, 30000) . "

Provide the extracted information in a clear, structured format.";

        return $this->generate($prompt, self::MAX_SUMMARY_OUTPUT_TOKENS);
    }
    
    /**
     * Classify document type
     */
    public function classifyDocument($text)
    {
        $prompt = "Classify this document into one of these categories:
- Letter of Application
- Application for Recognition / Renewal Form (application for organization recognition or renewal of accreditation of a student organization)
- Constitution and By-Laws
- Plan of Activities
- Accomplishment Report
- Certification of Officers (list of officers with positions: President, VP, Secretary, Treasurer, etc.)
- List of Members (a roster or list of student members)
- Adviser Commitment Form
- Dean Certification (certification letter signed by Dean)
- Financial Report
- Accreditation Form
- Other

IMPORTANT: Look for these clues:
- If you see officer POSITIONS (President, Vice President, Secretary, Treasurer, Auditor, PRO, etc.) with NAMES → \"Certification of Officers\"
- If you see a certification letter signed BY a Dean/Associate Dean → \"Dean Certification\"
- An application for organization recognition/renewal is NOT a \"Letter of Application\" → \"Application for Recognition / Renewal Form\"

Document excerpt:
" . substr($text, 0, 3000) . "

Respond with ONLY the category name, nothing else.";

        $result = trim($this->generate($prompt, self::MAX_CLASSIFY_OUTPUT_TOKENS));

        // Strip markdown fences and stray quotes from the category label.
        $result = preg_replace('/```[a-z]*/i', '', $result);
        $result = trim((string) $result, " \t\n\r\0\x0B\"'`");

        return $result;
    }
}
