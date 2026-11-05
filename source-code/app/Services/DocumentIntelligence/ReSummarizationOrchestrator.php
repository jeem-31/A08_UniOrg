<?php

namespace App\Services\DocumentIntelligence;

use App\Models\ProcessedDocument;
use App\Services\DocumentIntelligence\Contracts\DocumentIntelligenceProvider;
use App\Services\DocumentIntelligence\Contracts\FailureSanitizer;
use App\Services\DocumentIntelligence\Contracts\SourceDocumentReader;
use App\Services\DocumentIntelligence\Contracts\SourceTextExtractor;
use App\Services\DocumentIntelligence\Data\ExtractedDocumentText;
use App\Services\DocumentIntelligence\Data\ReadOnlyDocumentSource;
use App\Services\DocumentIntelligence\Exceptions\PermanentDocumentException;
use App\Services\DocumentIntelligence\Exceptions\TransientProcessingException;
use App\Services\DocumentIntelligence\Outcomes\PermanentDocumentFailureReason;
use App\Services\DocumentIntelligence\Outcomes\StaleCycleOutcome;
use DateTimeInterface;
use Throwable;

final class ReSummarizationOrchestrator
{
    public function __construct(
        private readonly DocumentAnalysisLifecycle $lifecycle,
        private readonly SupportedDocumentCatalog $catalog,
        private readonly SourceDocumentReader $sourceReader,
        private readonly SourceTextExtractor $textExtractor,
        private readonly DocumentIntelligenceProvider $intelligenceProvider,
        private readonly FailureSanitizer $failureSanitizer,
    ) {}

    public function refresh(
        int $analysisId,
        string $sourceVersion,
        string $summaryCycleId,
    ): ProcessedDocument|StaleCycleOutcome {
        $analysis = $this->activeAnalysis($analysisId, $sourceVersion, $summaryCycleId);
        if ($analysis === null) {
            return $this->stale($analysisId, $sourceVersion, $summaryCycleId);
        }

        $descriptor = $this->descriptor($analysis);
        if ($descriptor === null) {
            return $this->stale($analysisId, $sourceVersion, $summaryCycleId);
        }

        $source = null;

        try {
            $text = $this->retainedText($analysis);
            if ($text === null) {
                $source = $this->sourceReader->open($descriptor);
                $text = $this->textExtractor->extract($source);
            }

            if (trim($text->text) === '') {
                throw new PermanentDocumentException(PermanentDocumentFailureReason::Empty);
            }

            $candidate = $this->intelligenceProvider->summarize($text);
            $finishedAt = now();

            return $this->recordTransition(
                $this->lifecycle->completeResummarization(
                    $analysisId,
                    $descriptor,
                    $summaryCycleId,
                    $candidate,
                    $finishedAt,
                ),
                $analysisId,
                $sourceVersion,
                $summaryCycleId,
                $finishedAt,
            );
        } catch (TransientProcessingException $failure) {
            // Provider outages and rate limits are retryable: leave the
            // summary-refresh cycle in 'processing' state and let the queue
            // retry the job with backoff. Nothing is recorded as failed yet.
            throw $failure;
        } catch (Throwable $failure) {
            $finishedAt = now();

            return $this->recordTransition(
                $this->lifecycle->failResummarization(
                    $analysisId,
                    $descriptor,
                    $summaryCycleId,
                    $this->failureSanitizer->reviewerFailure($failure),
                    finishedAt: $finishedAt,
                ),
                $analysisId,
                $sourceVersion,
                $summaryCycleId,
                $finishedAt,
            );
        } finally {
            $this->cleanup($source);
        }
    }

    public function recordTerminalFailure(
        int $analysisId,
        string $sourceVersion,
        string $summaryCycleId,
        Throwable $failure,
    ): ProcessedDocument|StaleCycleOutcome {
        $analysis = $this->activeAnalysis($analysisId, $sourceVersion, $summaryCycleId);
        if ($analysis === null) {
            return $this->stale($analysisId, $sourceVersion, $summaryCycleId);
        }

        $descriptor = $this->descriptor($analysis);
        if ($descriptor === null) {
            return $this->stale($analysisId, $sourceVersion, $summaryCycleId);
        }

        return $this->lifecycle->failResummarization(
            $analysisId,
            $descriptor,
            $summaryCycleId,
            $this->failureSanitizer->reviewerFailure($failure),
        );
    }

    private function activeAnalysis(
        int $analysisId,
        string $sourceVersion,
        string $summaryCycleId,
    ): ?ProcessedDocument {
        return ProcessedDocument::query()
            ->whereKey($analysisId)
            ->where('source_version', $sourceVersion)
            ->where('output_source_version', $sourceVersion)
            ->where('processing_status', ProcessedDocument::STATUS_COMPLETED)
            ->where('resummarization_status', 'processing')
            ->where('resummarization_cycle_id', $summaryCycleId)
            ->first();
    }

    private function descriptor(ProcessedDocument $analysis): ?StoredDocumentDescriptor
    {
        if (! $this->catalog->supports((string) $analysis->document_field)) {
            return null;
        }

        return new StoredDocumentDescriptor(
            (int) $analysis->apply_org_id,
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

    private function retainedText(ProcessedDocument $analysis): ?ExtractedDocumentText
    {
        $text = trim((string) $analysis->extracted_text);

        return $text === '' ? null : new ExtractedDocumentText(
            $text,
            extractionMethod: 'retained-current-source',
        );
    }

    private function recordTransition(
        ProcessedDocument|StaleCycleOutcome $result,
        int $analysisId,
        string $sourceVersion,
        string $summaryCycleId,
        DateTimeInterface $finishedAt,
    ): ProcessedDocument|StaleCycleOutcome {
        if ($result instanceof StaleCycleOutcome) {
            $this->lifecycle->markAttemptStale(
                $analysisId,
                $sourceVersion,
                $summaryCycleId,
                1,
                $finishedAt,
            );
        }

        return $result;
    }

    private function stale(
        int $analysisId,
        string $sourceVersion,
        string $summaryCycleId,
    ): StaleCycleOutcome {
        $this->lifecycle->markAttemptStale(
            $analysisId,
            $sourceVersion,
            $summaryCycleId,
            1,
        );

        return new StaleCycleOutcome(
            $analysisId,
            $sourceVersion,
            $summaryCycleId,
            'analysis_source_or_summary_cycle_mismatch',
        );
    }

    private function cleanup(?ReadOnlyDocumentSource $source): void
    {
        if ($source === null || ! method_exists($source, 'cleanup')) {
            return;
        }

        try {
            $source->cleanup();
        } catch (Throwable) {
            // Cleanup is best-effort and never changes the stored source.
        }
    }
}
