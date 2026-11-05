<?php

namespace App\Http\Controllers\Osas;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDocumentAnalysis;
use App\Jobs\ReSummarizeDocument;
use App\Jobs\ReTagDocument;
use App\Models\ApplyOrg;
use App\Models\ApplyOrgDocumentArchive;
use App\Models\ArchivedProcessedDocument;
use App\Models\ProcessedDocument;
use App\Services\DocumentIntelligence\DocumentAnalysisLifecycle;
use App\Services\DocumentIntelligence\Exceptions\LifecycleConflictException;
use App\Services\DocumentIntelligence\Outcomes\StaleCycleOutcome;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;
use App\Services\DocumentIntelligence\SupportedDocumentCatalog;
use App\ViewModels\CurrentDocumentAnalysisPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentIntelligenceController extends Controller
{
    public function __construct(
        private readonly DocumentAnalysisLifecycle $lifecycle,
        private readonly SupportedDocumentCatalog $catalog,
    ) {}

    public function status(ApplyOrg $applyOrg, ProcessedDocument $analysis): JsonResponse
    {
        $analysis = $this->authorizedCurrent('view', $applyOrg, $analysis);
        $data = [
            'analysis_id' => (int) $analysis->getKey(),
            'status' => $analysis->processing_status,
            'output_available' => $analysis->hasCurrentOutput(),
            'updated_at' => $analysis->updated_at?->toIso8601String(),
        ];

        if ($analysis->processing_status === ProcessedDocument::STATUS_FAILED) {
            $data['failure_message'] = filled($analysis->error_message)
                ? $analysis->error_message
                : 'Document processing failed. Please try again.';
        }
        if ($analysis->hasCurrentOutput()) {
            $data['resummarization'] = $this->resummarizationMetadata($analysis);
        }

        return response()->json($data);
    }

    public function source(
        string $applyOrg,
        string $analysis,
    ): JsonResponse|StreamedResponse {
        // Union lookup for analyses source (Option B) — live or archived
        $liveApplyOrg = ApplyOrg::find($applyOrg);
        $archiveFromUrl = null;
        if ($liveApplyOrg === null) {
            $archiveFromUrl = \App\Models\ApplyOrgDocumentArchive::find($applyOrg);
            abort_if($archiveFromUrl === null, 404, 'The source document is unavailable.');
        }

        $liveAnalysis = ProcessedDocument::find($analysis);
        $archivedAnalysis = null;
        $isArchived = false;
        $analysisModel = $liveAnalysis;
        if ($analysisModel === null) {
            $archivedAnalysis = \App\Models\ArchivedProcessedDocument::find($analysis);
            if ($archivedAnalysis !== null) {
                $analysisModel = $archivedAnalysis;
                $isArchived = true;
            }
        }
        abort_if($analysisModel === null, 404, 'The source document is unavailable.');

        if ($isArchived) {
            // Authorize archived: osas_admin and same organization
            $user = Auth::user();
            abort_unless($user && $user->role === 'osas_admin', 403);
            // Allow if archived belongs to same org as liveApplyOrg or archiveFromUrl
            // For now, osas_admin can view any archived
            try {
                $stream = Storage::disk((string) $archivedAnalysis->storage_disk)
                    ->readStream((string) $archivedAnalysis->file_path);
            } catch (Throwable) {
                $stream = false;
            }
            if (! is_resource($stream)) {
                return response()->json(['message' => 'The source document is unavailable.'], 404);
            }
            $filename = $this->safeFilename((string) $archivedAnalysis->original_filename);
            return response()->stream(function () use ($stream): void {
                try { fpassthru($stream); } finally { fclose($stream); }
            }, 200, [
                'Content-Type' => $this->contentType($filename),
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $filename, $this->fallbackFilename($filename)),
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // Live path
        $applyOrgModel = $liveApplyOrg ?? ApplyOrg::find($applyOrg);
        abort_if($applyOrgModel === null, 404);
        $analysis = $this->authorizedCurrent('viewSource', $applyOrgModel, $liveAnalysis);

        try {
            $stream = Storage::disk((string) $analysis->storage_disk)
                ->readStream((string) $analysis->file_path);
        } catch (Throwable) {
            $stream = false;
        }

        if (! is_resource($stream)) {
            return response()->json(['message' => 'The source document is unavailable.'], 404);
        }

        $filename = $this->safeFilename((string) $analysis->original_filename);

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $this->contentType($filename),
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $filename,
                $this->fallbackFilename($filename),
            ),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function summary(ApplyOrg $applyOrg, ProcessedDocument $analysis): JsonResponse
    {
        $analysis = $this->authorizedCurrent('viewSummary', $applyOrg, $analysis);
        $output = $analysis->outputForCurrentSource();
        if ($output === null) {
            return $this->outputUnavailable($analysis);
        }

        return response()->json([
            'analysis_id' => (int) $analysis->getKey(),
            'ai_generated' => true,
            'advisory' => CurrentDocumentAnalysisPresenter::ADVISORY,
            'summary' => $output['summary'],
            'context' => is_array($output['key_information']) ? $output['key_information'] : [],
            'resummarization' => $this->resummarizationMetadata($analysis),
        ]);
    }

    public function details(ApplyOrg $applyOrg, ProcessedDocument $analysis): JsonResponse
    {
        $analysis = $this->authorizedCurrent('viewDetails', $applyOrg, $analysis);
        $output = $analysis->outputForCurrentSource();
        if ($output === null) {
            return $this->outputUnavailable($analysis);
        }

        return response()->json([
            'analysis_id' => (int) $analysis->getKey(),
            'ai_generated' => true,
            'advisory' => CurrentDocumentAnalysisPresenter::ADVISORY,
            'requirement_name' => $this->catalog->definition((string) $analysis->document_field)->label,
            'original_filename' => $analysis->original_filename,
            'detected_document_type' => $output['document_type_name'] ?: $output['document_type'],
            'status' => $analysis->processing_status,
            'tags' => is_array($output['tags']) ? $output['tags'] : [],
            'processed_at' => $analysis->processed_at?->toIso8601String(),
            'processing_duration_ms' => $analysis->processing_duration_ms,
        ]);
    }

    public function retry(ApplyOrg $applyOrg, ProcessedDocument $analysis): JsonResponse
    {
        $analysis = $this->authorizedCurrent('retry', $applyOrg, $analysis);
        $cycleId = (string) Str::uuid();

        try {
            $claimed = $this->lifecycle->startRetry(
                (int) $analysis->getKey(),
                $this->descriptor($applyOrg, $analysis),
                $cycleId,
            );
        } catch (LifecycleConflictException) {
            return response()->json([
                'message' => 'Document processing is already active or is not eligible for retry.',
            ], 409);
        }

        if ($claimed instanceof StaleCycleOutcome) {
            return response()->json([
                'message' => 'The document changed before the retry could start.',
            ], 409);
        }

        ProcessDocumentAnalysis::dispatch(
            (int) $claimed->getKey(),
            (string) $claimed->source_version,
            $cycleId,
        );

        return response()->json([
            'analysis_id' => (int) $claimed->getKey(),
            'status' => $claimed->processing_status,
            'message' => 'Document processing retry was queued.',
        ], 202);
    }

    public function resummarize(ApplyOrg $applyOrg, ProcessedDocument $analysis): JsonResponse
    {
        $analysis = $this->authorizedCurrent('resummarize', $applyOrg, $analysis);
        $cycleId = (string) Str::uuid();

        try {
            $claimed = $this->lifecycle->startResummarization(
                (int) $analysis->getKey(),
                $this->descriptor($applyOrg, $analysis),
                $cycleId,
            );
        } catch (LifecycleConflictException) {
            return response()->json([
                'message' => 'Document re-summarization is already active or is not eligible.',
            ], 409);
        }

        if ($claimed instanceof StaleCycleOutcome) {
            return response()->json([
                'message' => 'The document changed before re-summarization could start.',
            ], 409);
        }

        $current = $analysis->fresh();
        if ($current === null) {
            return response()->json([
                'message' => 'The document changed before re-summarization could start.',
            ], 409);
        }

        ReSummarizeDocument::dispatch(
            (int) $current->getKey(),
            (string) $current->source_version,
            $cycleId,
        );

        return response()->json([
            'analysis_id' => (int) $current->getKey(),
            'status' => $current->processing_status,
            'resummarization' => $this->resummarizationMetadata($current),
            'message' => 'Document re-summarization was queued.',
        ], 202);
    }

    public function retag(ApplyOrg $applyOrg, ProcessedDocument $analysis): JsonResponse
    {
        $analysis = $this->authorizedCurrent('retag', $applyOrg, $analysis);

        if (! $analysis->hasCurrentOutput()) {
            return response()->json([
                'message' => 'Tags can only be refreshed for a completed document.',
            ], 409);
        }

        ReTagDocument::dispatch(
            (int) $analysis->getKey(),
            (string) $analysis->source_version,
        );

        return response()->json([
            'analysis_id' => (int) $analysis->getKey(),
            'tags_updated_at' => $analysis->updated_at?->toIso8601String(),
            'message' => 'Tag refresh was queued. Previous tags stay until the new tags arrive.',
        ], 202);
    }

    /** @return array<string, mixed> */
    private function resummarizationMetadata(ProcessedDocument $analysis): array
    {
        return [
            'status' => $analysis->resummarization_status,
            'in_progress' => $analysis->resummarization_status === 'processing',
            'failure_message' => $analysis->resummarization_status === 'failed'
                ? $analysis->resummarization_error_message
                : null,
            'last_result_changed' => $analysis->last_resummarization_changed,
            'summary_version' => (int) $analysis->summary_version,
            'summary_processed_at' => $analysis->summary_processed_at?->toIso8601String(),
        ];
    }

    private function authorizedCurrent(
        string $ability,
        ApplyOrg $applyOrg,
        ProcessedDocument $analysis,
    ): ProcessedDocument {
        $this->authorize($ability, [$analysis, $applyOrg]);

        $current = $analysis->fresh();
        abort_if($current === null, 404);
        $this->authorize($ability, [$current, $applyOrg->fresh() ?? $applyOrg]);

        return $current;
    }

    private function descriptor(
        ApplyOrg $applyOrg,
        ProcessedDocument $analysis,
    ): StoredDocumentDescriptor {
        return new StoredDocumentDescriptor(
            (int) $applyOrg->getKey(),
            (string) $analysis->document_field,
            (string) $analysis->document_slot,
            $this->catalog->definition((string) $analysis->document_field)->label,
            (string) $analysis->original_filename,
            (string) $analysis->storage_disk,
            (string) $analysis->file_path,
            (string) $analysis->source_version,
            (string) $analysis->source_fingerprint,
        );
    }

    private function outputUnavailable(ProcessedDocument $analysis): JsonResponse
    {
        return response()->json([
            'message' => 'Generated output is not available for this document.',
            'status' => $analysis->processing_status,
        ], 409);
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename);

        return is_string($filename) && $filename !== '' ? $filename : 'document';
    }

    private function fallbackFilename(string $filename): string
    {
        $extension = preg_replace('/[^A-Za-z0-9]/', '', pathinfo($filename, PATHINFO_EXTENSION));

        return 'document'.($extension === '' ? '' : '.'.strtolower($extension));
    }

    private function contentType(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }
}
