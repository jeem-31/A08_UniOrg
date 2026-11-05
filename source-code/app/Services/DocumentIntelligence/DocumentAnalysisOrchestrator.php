<?php

namespace App\Services\DocumentIntelligence;

use App\Models\DocumentProcessingAttempt;
use App\Models\ProcessedDocument;
use App\Services\DocumentIntelligence\Contracts\AIOutputValidator;
use App\Services\DocumentIntelligence\Contracts\DocumentIntelligenceProvider;
use App\Services\DocumentIntelligence\Contracts\FailureSanitizer;
use App\Services\DocumentIntelligence\Contracts\SourceDocumentReader;
use App\Services\DocumentIntelligence\Contracts\SourceTextExtractor;
use App\Services\DocumentIntelligence\Data\ReadOnlyDocumentSource;
use App\Services\DocumentIntelligence\Exceptions\InvalidAIOutputException;
use App\Services\DocumentIntelligence\Exceptions\PermanentDocumentException;
use App\Services\DocumentIntelligence\Exceptions\TransientProcessingException;
use App\Services\DocumentIntelligence\Outcomes\PermanentDocumentFailureReason;
use App\Services\DocumentIntelligence\Outcomes\StaleCycleOutcome;
use DateTimeInterface;
use Throwable;

final class DocumentAnalysisOrchestrator
{
    public function __construct(
        private readonly DocumentAnalysisLifecycle $lifecycle,
        private readonly SupportedDocumentCatalog $catalog,
        private readonly SourceDocumentReader $sourceReader,
        private readonly SourceTextExtractor $textExtractor,
        private readonly DocumentIntelligenceProvider $intelligenceProvider,
        private readonly AIOutputValidator $outputValidator,
        private readonly FailureSanitizer $failureSanitizer,
    ) {}

    public function process(
        int $analysisId,
        string $sourceVersion,
        string $cycleId,
    ): ProcessedDocument|StaleCycleOutcome {
        $descriptor = $this->descriptor($analysisId, $sourceVersion);
        if ($descriptor === null) {
            return new StaleCycleOutcome($analysisId, $sourceVersion, $cycleId, 'analysis_or_source_mismatch');
        }

        $attempt = $this->lifecycle->claimPending($analysisId, $descriptor, $cycleId);
        if ($attempt instanceof StaleCycleOutcome) {
            return $attempt;
        }

        $source = null;

        try {
            $source = $this->sourceReader->open($descriptor);
            $extractedText = $this->textExtractor->extract($source);

            if (trim($extractedText->text) === '') {
                throw new PermanentDocumentException(PermanentDocumentFailureReason::Empty);
            }

            $candidate = $this->intelligenceProvider->analyze($extractedText);
            $output = $this->outputValidator->validate($candidate);
            $finishedAt = now();

            return $this->recordTransition(
                $this->lifecycle->complete($analysisId, $descriptor, $cycleId, $output, $finishedAt),
                $attempt,
                $finishedAt,
            );
        } catch (TransientProcessingException $failure) {
            $finishedAt = now();

            return $this->recordTransition(
                $this->lifecycle->releaseTransient(
                    $analysisId,
                    $descriptor,
                    $cycleId,
                    $this->failureSanitizer->reviewerFailure($failure),
                    finishedAt: $finishedAt,
                ),
                $attempt,
                $finishedAt,
            );
        } catch (PermanentDocumentException|InvalidAIOutputException $failure) {
            return $this->recordFailure($analysisId, $descriptor, $cycleId, $attempt, $failure);
        } catch (Throwable $failure) {
            return $this->recordFailure($analysisId, $descriptor, $cycleId, $attempt, $failure);
        } finally {
            $this->cleanup($source);
        }
    }

    public function recordTerminalFailure(
        int $analysisId,
        string $sourceVersion,
        string $cycleId,
        Throwable $failure,
    ): ProcessedDocument|StaleCycleOutcome {
        $descriptor = $this->descriptor($analysisId, $sourceVersion);
        if ($descriptor === null) {
            return new StaleCycleOutcome($analysisId, $sourceVersion, $cycleId, 'analysis_or_source_mismatch');
        }

        return $this->lifecycle->failTerminal(
            $analysisId,
            $descriptor,
            $cycleId,
            $this->failureSanitizer->reviewerFailure($failure),
        );
    }

    private function descriptor(int $analysisId, string $sourceVersion): ?StoredDocumentDescriptor
    {
        $analysis = ProcessedDocument::query()
            ->whereKey($analysisId)
            ->where('source_version', $sourceVersion)
            ->first();

        if ($analysis === null || ! $this->catalog->supports((string) $analysis->document_field)) {
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

    private function recordFailure(
        int $analysisId,
        StoredDocumentDescriptor $descriptor,
        string $cycleId,
        DocumentProcessingAttempt $attempt,
        Throwable $failure,
    ): ProcessedDocument|StaleCycleOutcome {
        $finishedAt = now();

        return $this->recordTransition(
            $this->lifecycle->fail(
                $analysisId,
                $descriptor,
                $cycleId,
                $this->failureSanitizer->reviewerFailure($failure),
                finishedAt: $finishedAt,
            ),
            $attempt,
            $finishedAt,
        );
    }

    private function recordTransition(
        ProcessedDocument|StaleCycleOutcome $result,
        DocumentProcessingAttempt $attempt,
        DateTimeInterface $finishedAt,
    ): ProcessedDocument|StaleCycleOutcome {
        if ($result instanceof StaleCycleOutcome) {
            $this->lifecycle->markAttemptStale(
                $attempt->processed_document_id,
                $attempt->source_version,
                $attempt->cycle_id,
                $attempt->ordinal,
                $finishedAt,
            );
        }

        return $result;
    }

    private function cleanup(?ReadOnlyDocumentSource $source): void
    {
        if ($source === null || ! method_exists($source, 'cleanup')) {
            return;
        }

        try {
            $source->cleanup();
        } catch (Throwable) {
            // Cleanup is best-effort and must never mutate or replace the stored source.
        }
    }
}
