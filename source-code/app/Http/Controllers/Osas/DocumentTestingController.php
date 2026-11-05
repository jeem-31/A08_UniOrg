<?php

namespace App\Http\Controllers\Osas;

use App\Http\Controllers\Controller;
use App\Services\DocumentProcessorService;
use App\Services\GeminiDocumentProcessor;
use App\Services\GroqService;
use App\Services\GoogleCloudNLPService;
use App\Services\GoogleCloudVisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Isolated document-processing test lab.
 *
 * This controller never reads or writes organization applications or analysis
 * records. Production application processing is registered by upload workflows
 * and executed exclusively through queued document-intelligence jobs.
 */
final class DocumentTestingController extends Controller
{
    public function show(): View
    {
        return view('osas.document-testing');
    }

    public function process(
        Request $request,
        DocumentProcessorService $documentProcessor,
        GeminiDocumentProcessor $geminiProcessor,
        GoogleCloudVisionService $vision,
        GoogleCloudNLPService $nlp,
        GroqService $groq,
    ): JsonResponse {
        set_time_limit(120);

        $validated = $request->validate([
            'document' => 'required|file|mimes:pdf,docx,jpg,jpeg,png|max:10240',
            'processing_mode' => 'required|in:rule_based,gemini,google_cloud',
        ]);

        $fullPath = null;

        try {
            $file = $validated['document'];
            $originalName = $file->getClientOriginalName();
            $mode = $validated['processing_mode'];
            $tempPath = $file->store('temp', 'local');
            $fullPath = storage_path('app/'.$tempPath);
            $startedAt = microtime(true);

            $result = match ($mode) {
                'rule_based' => $this->processRuleBased($documentProcessor, $fullPath, $originalName),
                'gemini' => $geminiProcessor->processDocument($fullPath, $originalName),
                'google_cloud' => $this->processWithGoogleCloud(
                    $fullPath,
                    $originalName,
                    $vision,
                    $nlp,
                    $groq,
                ),
            };

            $totalTime = round((microtime(true) - $startedAt) * 1000, 2);

            if (! ($result['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Processing failed: '.($result['error'] ?? 'Unknown processing error'),
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Document processed successfully',
                'processing_mode' => $mode,
                'total_time_ms' => $totalTime,
                'data' => [
                    'original_filename' => $originalName,
                    'method' => $result['method'] ?? $mode,
                    'document_type' => $result['document_type'],
                    'document_type_name' => $result['document_type_name'],
                    'summary' => $result['summary'],
                    'tags' => $result['tags'],
                    'key_information' => $result['key_information'],
                    'extracted_text' => $result['extracted_text'],
                    'confidence_score' => null,
                    'processed_at' => $result['processed_at'],
                    'processing_time_ms' => $result['processing_time_ms'] ?? $totalTime,
                    'sentiment' => $result['sentiment'] ?? null,
                    'entities' => $result['entities'] ?? null,
                    'comparison' => $result['comparison'] ?? null,
                    'all_ml_scores' => $result['all_ml_scores'] ?? null,
                ],
            ]);
        } catch (Throwable $failure) {
            Log::error('Test document processing error: '.$failure->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$failure->getMessage(),
            ], 500);
        } finally {
            if ($fullPath !== null && is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    private function processRuleBased(
        DocumentProcessorService $processor,
        string $path,
        string $originalName,
    ): array {
        $result = $processor->processDocument($path, $originalName);
        $result['method'] = 'rule_based';

        return $result;
    }

    private function processWithGoogleCloud(
        string $path,
        string $originalName,
        GoogleCloudVisionService $vision,
        GoogleCloudNLPService $nlp,
        GroqService $groq,
    ): array {
        $startedAt = microtime(true);

        try {
            if (! $vision->isEnabled()) {
                throw new \RuntimeException('Google Cloud Vision is not enabled.');
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $ocr = $extension === 'pdf'
                ? $vision->extractTextFromPdf($path, 1)
                : $vision->extractText($path);

            if (! ($ocr['success'] ?? false)) {
                throw new \RuntimeException('OCR failed: '.($ocr['error'] ?? 'Unknown OCR error'));
            }

            $text = trim((string) ($ocr['text'] ?? ''));
            if ($text === '') {
                throw new \UnexpectedValueException('OCR returned no source text.');
            }

            $entities = [];
            if ($nlp->isEnabled()) {
                $nlpResult = $nlp->extractEntities($text);
                if ($nlpResult['success'] ?? false) {
                    $entities = $nlpResult['entities'] ?? [];
                }
            }
            if (! $groq->isConfigured()) {
                throw new \RuntimeException('Groq AI is not configured.');
            }

            $analysis = $groq->summarizeDocument($text);
            $documentType = $this->mapDocumentType((string) ($analysis['document_type'] ?? ''));
            $summary = trim((string) ($analysis['summary'] ?? ''));
            if ($summary === '') {
                throw new \UnexpectedValueException('Groq AI returned an empty summary.');
            }

            return [
                'success' => true,
                'method' => 'google_cloud_ai',
                'document_type' => $documentType['type'],
                'document_type_name' => $documentType['name'],
                'summary' => $summary,
                'tags' => $this->tags($analysis),
                'key_information' => $this->context($analysis, $entities),
                'extracted_text' => $text,
                'processed_at' => now()->toDateTimeString(),
                'processing_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                'entities' => $entities,
            ];
        } catch (Throwable $failure) {
            Log::error('Google Cloud test-lab processing error: '.$failure->getMessage());

            return ['success' => false, 'error' => $failure->getMessage()];
        }
    }

    private function mapDocumentType(string $providerType): array
    {
        $types = [
            'Letter of Application' => ['type' => 'letter_of_application', 'name' => 'Letter of Application'],
            'Constitution and By-Laws' => ['type' => 'constitution_bylaws', 'name' => 'Constitution and By-Laws'],
            'Plan of Activities' => ['type' => 'program_of_activities', 'name' => 'Plan of Activities'],
            'Accomplishment Report' => ['type' => 'accomplishment_report', 'name' => 'Accomplishment Report'],
            'Certification of Officers' => ['type' => 'certification_of_officers', 'name' => 'Certification of Officers'],
            'Adviser Commitment Form' => ['type' => 'adviser_commitment_form', 'name' => 'Adviser Commitment Form'],
            'Dean Certification' => ['type' => 'dean_certification', 'name' => 'Dean Certification'],
            'Financial Report' => ['type' => 'financial_report', 'name' => 'Financial Report'],
            'Accreditation Form' => ['type' => 'accreditation_form', 'name' => 'Accreditation Form'],
        ];

        foreach ($types as $label => $mapped) {
            if (strcasecmp($providerType, $label) === 0
                || ($providerType !== '' && stripos($label, $providerType) !== false)
                || ($label !== '' && stripos($providerType, $label) !== false)) {
                return $mapped;
            }
        }

        return ['type' => 'unknown', 'name' => $providerType !== '' ? $providerType : 'Unknown Document'];
    }

    private function tags(array $analysis): array
    {
        $tags = [];
        $organization = trim((string) ($analysis['organization_name'] ?? ''));
        if ($organization !== '') {
            $tags[] = $organization;
        }

        foreach (($analysis['tags'] ?? []) as $tag) {
            $tag = trim((string) $tag);
            if ($tag !== '' && ! in_array(strtolower($tag), array_map('strtolower', $tags), true)) {
                $tags[] = $tag;
            }
        }

        return array_slice($tags, 0, 10);
    }

    private function context(array $analysis, array $entities): array
    {
        $context = array_filter([
            'organization' => $analysis['organization_name'] ?? null,
            'school_year' => $analysis['school_year'] ?? null,
            'dates' => $analysis['key_dates'] ?? null,
            'details' => $analysis['key_information'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        $organizations = [];
        $persons = [];
        foreach ($entities as $entity) {
            if (($entity['salience'] ?? 0) <= 0.08) {
                continue;
            }

            if (($entity['type'] ?? null) === 3) {
                $organizations[] = $entity['name'];
            } elseif (($entity['type'] ?? null) === 1) {
                $persons[] = $entity['name'];
            }
        }

        if ($organizations !== []) {
            $context['entities_organizations'] = $organizations;
        }
        if ($persons !== []) {
            $context['entities_persons'] = $persons;
        }

        return $context;
    }
}
