<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Document analysis provider backed by Groq's OpenAI-compatible API
 * (llama-3.3-70b-versatile). Exposes the same public API as the previous
 * GeminiService so callers remain unchanged.
 */
class GroqService
{
    private $apiKey;
    private $baseUrl = 'https://api.groq.com/openai/v1';
    private $model = 'openai/gpt-oss-20b';

    // Groq free tier budgets ~12k TPM for this model. 22k chars is about
    // 5.5k-6k tokens, so prompt + output headroom stays under the limit and
    // two documents can be processed within the same minute.
    private const MAX_DOCUMENT_TEXT_LENGTH = 22000;
    private const MAX_SUMMARY_OUTPUT_TOKENS = 4096;
    private const MAX_CLASSIFY_OUTPUT_TOKENS = 2048;

    // Multi-key pool for sustainable rotation (env GROQ_API_KEYS comma-separated, fallback GROQ_API_KEY)
    private array $keyPool = [];
    private int $currentKeyIndex = 0;

    public function __construct()
    {
        $this->apiKey = env('GROQ_API_KEY');
        $rawPool = env('GROQ_API_KEYS') ?: $this->apiKey;
        $this->keyPool = array_values(array_filter(array_map('trim', explode(',', (string) $rawPool))));
        if (empty($this->keyPool) && !empty($this->apiKey)) {
            $this->keyPool = [$this->apiKey];
        }
        // Load persisted index (for failover across requests)
        try {
            $cached = \Illuminate\Support\Facades\Cache::get('groq_key_index');
            if (is_numeric($cached) && isset($this->keyPool[(int)$cached])) {
                $this->currentKeyIndex = (int)$cached;
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Check if the Groq API is configured
     */
    public function isConfigured()
    {
        return !empty($this->keyPool);
    }

    private function currentKey(): string
    {
        if (empty($this->keyPool)) return (string) $this->apiKey;
        // Skip keys that are in cooldown (limited recently)
        for ($i = 0; $i < count($this->keyPool); $i++) {
            $idx = ($this->currentKeyIndex + $i) % count($this->keyPool);
            try {
                $limitedUntil = \Illuminate\Support\Facades\Cache::get('groq_key_'.$idx.'_limited_until');
                if ($limitedUntil && strtotime((string)$limitedUntil) > time()) {
                    continue;
                }
            } catch (\Throwable $e) {}
            $this->currentKeyIndex = $idx;
            return $this->keyPool[$idx];
        }
        return $this->keyPool[$this->currentKeyIndex] ?? (string) $this->apiKey;
    }

    private function markKeyLimited(int $index, $response): void
    {
        try {
            $retryAfter = 60;
            if ($response && method_exists($response, 'header')) {
                $ra = $response->header('Retry-After');
                if (is_numeric($ra)) $retryAfter = (int)$ra;
            }
            \Illuminate\Support\Facades\Cache::put('groq_key_'.$index.'_limited_until', date('c', time() + $retryAfter), $retryAfter + 5);
            Log::warning("[GROQ] Key index {$index} rate-limited, cooling down {$retryAfter}s");
        } catch (\Throwable $e) {}
    }

    private function rotateKey(): void
    {
        if (empty($this->keyPool)) return;
        $this->currentKeyIndex = ($this->currentKeyIndex + 1) % count($this->keyPool);
        try { \Illuminate\Support\Facades\Cache::put('groq_key_index', $this->currentKeyIndex, 3600); } catch (\Throwable $e) {}
        Log::warning("[GROQ] Rotating to key index {$this->currentKeyIndex}");
    }

    /**
     * Generate content using one provider request. Retry accounting belongs to
     * the document-analysis orchestrator, not this provider client.
     * Now with multi-key rotation: on 429/rate_limit, switch to next key and retry (no Gemini fallback).
     */
    public function generate($prompt, $maxTokens = 1024, $jsonMode = false)
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Groq AI is not configured.');
        }

        $payload = [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $maxTokens,
            'temperature' => 0.7,
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $maxAttempts = max(1, count($this->keyPool));
        $lastException = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $key = $this->currentKey();
            $keyIndex = $this->currentKeyIndex;

            Log::info('[GROQ] Sending request', [
                'prompt_length' => strlen($prompt),
                'max_tokens' => $maxTokens,
                'json_mode' => $jsonMode,
                'key_index' => $keyIndex,
                'attempt' => $attempt + 1 . '/' . $maxAttempts,
            ]);

            $response = Http::timeout(120)
                ->withToken($key)
                ->post("{$this->baseUrl}/chat/completions", $payload);

            if ($response->successful()) {
                $generatedText = $response->json('choices.0.message.content');
                if (! is_string($generatedText) || trim($generatedText) === '') {
                    throw new \UnexpectedValueException('Unexpected Groq API response format.');
                }
                // Success — persist current index
                try { \Illuminate\Support\Facades\Cache::put('groq_key_index', $this->currentKeyIndex, 3600); } catch (\Throwable $e) {}
                return $generatedText;
            }

            // Failed — check if rate limit / auth that warrants rotation
            $status = $response->status();
            $message = trim((string) $response->json('error.message', ''));
            $code = trim((string) $response->json('error.code', ''));
            $isRateLimit = $status === 429 || $code === 'rate_limit_exceeded' || $code === 'rate_limit_error' || $code === 'quota_exceeded' || str_contains(strtolower($message), 'rate limit') || str_contains(strtolower($message), 'too many requests') || str_contains(strtolower($message), 'quota');
            $isAuthLimit = in_array($status, [401, 402, 403], true);

            Log::error('[GROQ] API request failed', [
                'status' => $status,
                'code' => $code,
                'message' => $message,
                'key_index' => $keyIndex,
            ]);

            if (($isRateLimit || $isAuthLimit) && $attempt < $maxAttempts - 1) {
                $this->markKeyLimited($keyIndex, $response);
                $this->rotateKey();
                Log::warning("[GROQ] Rotating key {$keyIndex} -> {$this->currentKeyIndex} due to {$status} {$code}");
                continue;
            }

            $detail = $message !== '' ? ": {$message}" : '';
            $detail .= $code !== '' ? " (code: {$code})" : '';
            $lastException = new \RuntimeException("Groq API request failed with status {$status}{$detail}.");

            // For 5xx, do not rotate, just fail for orchestrator retry
            throw $lastException;
        }

        throw $lastException ?? new \RuntimeException('Groq API request failed: all keys exhausted.');
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

2. **Organization Name**: Extract the name of the student organization (use Title Case with lowercase connecting words like \"of\" and \"the\", e.g., \"Junior Philippine Computer Society\")

3. **School Year**: Extract the academic year (e.g., 2024-2025)

4. **Key Dates**: List any important dates mentioned (event dates, meeting dates, effectivity dates). EXCLUDE form revision dates, form numbers, and boilerplate.

5. **Summary**: Provide a concise 2-3 sentence summary of the document's main purpose and content

6. **Key Information**: Extract any other relevant details (officers, budget amounts, activities, etc.)

7. **Tags**: Generate 3 to 8 tags as a JSON array of plain strings, with the exact document type as the first tag.
   Rules:
   - lowercase, 1-3 words each (e.g. \"accomplishment report\", \"budget\", \"feeding program\");
   - tag only concepts actually present in the document: its topics, activities, amounts, people/roles, events, dates, and the school year or org acronym when clearly stated;
   - NEVER use generic filler tags like \"document\", \"report\", \"file\", \"paper\", \"misc\", or \"osas\";
   - NEVER invent topics not found in the text;
   - no duplicates (case-insensitive).

If any field is not present in the document, use an empty string (or an empty array for lists) — never use \"Not specified\".

Format your response as JSON with these keys: document_type, organization_name, school_year, key_dates, summary, key_information, tags

Document Text:
" . substr($text, 0, self::MAX_DOCUMENT_TEXT_LENGTH) . "

Respond ONLY with valid JSON, no additional text.";

        try {
            $response = $this->generate($prompt, self::MAX_SUMMARY_OUTPUT_TOKENS, true);

            Log::info('🟣 [GROQ] Raw response received', [
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
                Log::info('✅ [GROQ] JSON parsed successfully', [
                    'has_summary' => isset($parsed['summary']),
                    'has_document_type' => isset($parsed['document_type'])
                ]);

                return $parsed;
            }

            Log::warning('⚠️ [GROQ] JSON parse error', [
                'error' => json_last_error_msg(),
                'json_preview' => substr($response, 0, 200)
            ]);

            throw new \UnexpectedValueException('Groq returned malformed document-analysis JSON.');
        } catch (\Exception $e) {
            Log::error('❌ [GROQ] Summarization failed', [
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
" . substr($text, 0, self::MAX_DOCUMENT_TEXT_LENGTH) . "

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
