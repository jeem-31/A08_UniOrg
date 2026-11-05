<?php

namespace App\Services\DocumentIntelligence;

use App\Events\DocumentAnalysisStatusUpdated;
use App\Models\ApplyOrg;
use App\Models\DocumentProcessingAttempt;
use App\Models\ProcessedDocument;
use App\Services\DocumentIntelligence\Data\CandidateSummary;
use App\Services\DocumentIntelligence\Data\SanitizedFailure;
use App\Services\DocumentIntelligence\Data\ValidatedAIOutput;
use App\Services\DocumentIntelligence\Exceptions\LifecycleConflictException;
use App\Services\DocumentIntelligence\Outcomes\StaleCycleOutcome;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DocumentAnalysisLifecycle
{
    private const MAX_CYCLE_ATTEMPTS = 3;

    public function __construct(private readonly SupportedDocumentCatalog $catalog) {}

    public function claimPending(
        int $analysisId,
        StoredDocumentDescriptor $descriptor,
        string $cycleId,
        ?DateTimeInterface $startedAt = null,
    ): DocumentProcessingAttempt|StaleCycleOutcome {
        return DB::transaction(function () use ($analysisId, $descriptor, $cycleId, $startedAt) {
            $analysis = $this->lockMatchingDescriptor($analysisId, $descriptor);
            if (! $this->isClaimable($analysis, $cycleId)) {
                return $this->stale($analysisId, $descriptor->sourceVersion, $cycleId, 'not_claimable');
            }

            $ordinal = $analysis->cycle_attempt_count + 1;
            $duplicate = DocumentProcessingAttempt::query()
                ->where('processed_document_id', $analysis->id)
                ->where('cycle_id', $cycleId)
                ->where('ordinal', $ordinal)
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                return $this->stale($analysisId, $descriptor->sourceVersion, $cycleId, 'duplicate_attempt');
            }

            $now = $this->time($startedAt);
            $attempt = (new DocumentProcessingAttempt)->forceFill([
                'processed_document_id' => $analysis->id,
                'cycle_id' => $cycleId,
                'source_version' => $descriptor->sourceVersion,
                'operation' => $analysis->cycle_kind,
                'ordinal' => $ordinal,
                'outcome' => 'running',
                'started_at' => $now,
            ]);
            $attempt->save();

            $analysis->forceFill([
                'processing_status' => ProcessedDocument::STATUS_PROCESSING,
                'cycle_attempt_count' => $ordinal,
                'processing_started_at' => $analysis->processing_started_at ?? $now,
            ])->save();

            $this->broadcastStatus($analysis);

            return $attempt;
        });
    }

    public function releaseTransient(
        int $analysisId,
        StoredDocumentDescriptor $descriptor,
        string $cycleId,
        SanitizedFailure $failure,
        ?string $providerStatusCode = null,
        ?DateTimeInterface $finishedAt = null,
    ): ProcessedDocument|StaleCycleOutcome {
        return DB::transaction(function () use (
            $analysisId,
            $descriptor,
            $cycleId,
            $failure,
            $providerStatusCode,
            $finishedAt,
        ) {
            $analysis = $this->lockMatchingActiveProcessing($analysisId, $descriptor, $cycleId);
            if ($analysis === null) {
                return $this->stale($analysisId, $descriptor->sourceVersion, $cycleId);
            }

            $now = $this->time($finishedAt);
            if (! $this->finishAttempt($analysis, $cycleId, 'failed', $now, $failure, $providerStatusCode)) {
                return $this->stale($analysisId, $descriptor->sourceVersion, $cycleId, 'attempt_not_running');
            }

            if ($analysis->cycle_attempt_count < self::MAX_CYCLE_ATTEMPTS) {
                $analysis->forceFill([
                    'processing_status' => ProcessedDocument::STATUS_PENDING,
                ])->save();

                $this->broadcastStatus($analysis);

                return $analysis;
            }

            $analysis->forceFill([
                'processing_status' => ProcessedDocument::STATUS_FAILED,
                'failure_code' => $this->failureCode($failure),
                'error_message' => $failure->reviewerMessage,
                'active_cycle_id' => null,
                'cycle_kind' => null,
                'processing_duration_ms' => $this->duration($analysis->processing_started_at, $now),
            ])->save();

            $this->broadcastStatus($analysis);

            return $analysis;
        });
    }

    public function complete(
        int $analysisId,
        StoredDocumentDescriptor $descriptor,
        string $cycleId,
        ValidatedAIOutput $output,
        ?DateTimeInterface $finishedAt = null,
    ): ProcessedDocument|StaleCycleOutcome {
        return DB::transaction(function () use ($analysisId, $descriptor, $cycleId, $output, $finishedAt) {
            $analysis = $this->lockMatchingActiveProcessing($analysisId, $descriptor, $cycleId);
            if ($analysis === null) {
                return $this->stale($analysisId, $descriptor->sourceVersion, $cycleId);
            }

            $now = $this->positiveCompletionTime($finishedAt);
            if (! $this->finishAttempt($analysis, $cycleId, 'succeeded', $now)) {
                return $this->stale($analysisId, $descriptor->sourceVersion, $cycleId, 'attempt_not_running');
            }

            $analysis->forceFill([
                'document_type' => $output->documentType,
                'document_type_name' => $output->documentTypeName,
                'summary' => $output->summary,
                'tags' => $output->tags,
                'key_information' => $output->context,
                'extracted_text' => $output->extractedText->text,
                'confidence_score' => null,
                'output_source_version' => $descriptor->sourceVersion,
                'processed_at' => $now,
                'summary_processed_at' => $now,
                'summary_version' => max(1, (int) $analysis->summary_version),
                'processing_duration_ms' => $this->duration($analysis->processing_started_at, $now),
                'failure_code' => null,
                'error_message' => null,
                'processing_status' => ProcessedDocument::STATUS_COMPLETED,
                'active_cycle_id' => null,
                'cycle_kind' => null,
            ])->save();

            $this->broadcastStatus($analysis);

            return $analysis;
        });
    }

    public function fail(
        int $analysisId,
        StoredDocumentDescriptor $descriptor,
        string $cycleId,
        SanitizedFailure $failure,
        ?string $providerStatusCode = null,
        ?DateTimeInterface $finishedAt = null,
    ): ProcessedDocument|StaleCycleOutcome {
        return DB::transaction(function () use (
            $analysisId,
            $descriptor,
            $cycleId,
            $failure,
            $providerStatusCode,
            $finishedAt,
        ) {
            $analysis = $this->lockMatchingActiveProcessing($analysisId, $descriptor, $cycleId);
            if ($analysis === null) {
                return $this->stale($analysisId, $descriptor->sourceVersion, $cycleId);
            }

            $now = $this->time($finishedAt);
            if (! $this->finishAttempt($analysis, $cycleId, 'failed', $now, $failure, $providerStatusCode)) {
                return $this->stale($analysisId, $descriptor->sourceVersion, $cycleId, 'attempt_not_running');
            }

            $analysis->forceFill([
                'processing_status' => ProcessedDocument::STATUS_FAILED,
                'failure_code' => $this->failureCode($failure),
                'error_message' => $failure->reviewerMessage,
                'active_cycle_id' => null,
                'cycle_kind' => null,
                'processing_duration_ms' => $this->duration($analysis->processing_started_at, $now),
            ])->save();

            $this->broadcastStatus($analysis);

            return $analysis;
        });
    }

    /**
     * Fails a queue delivery only while it still owns the current full-processing cycle.
     */
    public function failTerminal(
        int $analysisId,
        StoredDocumentDescriptor $descriptor,
        string $cycleId,
        SanitizedFailure $failure,
        ?DateTimeInterface $finishedAt = null,
    ): ProcessedDocument|StaleCycleOutcome {
        return DB::transaction(function () use ($analysisId, $descriptor, $cycleId, $failure, $finishedAt) {
            $analysis = $this->lockMatchingDescriptor($analysisId, $descriptor);
            if ($analysis === null
                || ! in_array($analysis->processing_status, [
                    ProcessedDocument::STATUS_PENDING,
                    ProcessedDocument::STATUS_PROCESSING,
                ], true)
                || ! hash_equals((string) $analysis->active_cycle_id, $cycleId)
                || ! in_array($analysis->cycle_kind, ['initial', 'retry'], true)) {
                return $this->stale($analysisId, $descriptor->sourceVersion, $cycleId);
            }

            $now = $this->time($finishedAt);
            if ($analysis->processing_status === ProcessedDocument::STATUS_PROCESSING
                && ! $this->finishAttempt($analysis, $cycleId, 'failed', $now, $failure)) {
                return $this->stale($analysisId, $descriptor->sourceVersion, $cycleId, 'attempt_not_running');
            }

            $analysis->forceFill([
                'processing_status' => ProcessedDocument::STATUS_FAILED,
                'failure_code' => $this->failureCode($failure),
                'error_message' => $failure->reviewerMessage,
                'active_cycle_id' => null,
                'cycle_kind' => null,
                'processing_duration_ms' => $this->duration($analysis->processing_started_at, $now),
            ])->save();

            $this->broadcastStatus($analysis);

            return $analysis;
        });
    }

    /**
     * Starts a new full-processing cycle without changing source or generated-detail fields.
     *
     * @throws LifecycleConflictException when the record is not currently failed
     */
    public function startRetry(
        int $analysisId,
        StoredDocumentDescriptor $descriptor,
        ?string $cycleId = null,
    ): ProcessedDocument|StaleCycleOutcome {
        $cycleId ??= (string) Str::uuid();

        return DB::transaction(function () use ($analysisId, $descriptor, $cycleId) {
            $analysis = $this->lockMatchingDescriptor($analysisId, $descriptor);
            if ($analysis === null) {
                return $this->stale($analysisId, $descriptor->sourceVersion, $cycleId);
            }
            if ($analysis->processing_status !== ProcessedDocument::STATUS_FAILED) {
                throw LifecycleConflictException::retryRequiresFailed($analysisId, $analysis->processing_status);
            }

            $analysis->forceFill([
                'processing_status' => ProcessedDocument::STATUS_PENDING,
                'active_cycle_id' => $cycleId,
                'cycle_kind' => 'retry',
                'cycle_attempt_count' => 0,
                'processing_started_at' => null,
                'processing_duration_ms' => null,
                'failure_code' => null,
                'error_message' => null,
            ])->save();

            return $analysis;
        });
    }

    /**
     * Starts an independent summary-refresh cycle while leaving full processing completed.
     *
     * @throws LifecycleConflictException when current completed output is unavailable or a refresh is active
     */
    public function startResummarization(
        int $analysisId,
        StoredDocumentDescriptor $descriptor,
        ?string $cycleId = null,
        ?DateTimeInterface $startedAt = null,
    ): DocumentProcessingAttempt|StaleCycleOutcome {
        $cycleId ??= (string) Str::uuid();

        return DB::transaction(function () use ($analysisId, $descriptor, $cycleId, $startedAt) {
            $analysis = $this->lockMatchingDescriptor($analysisId, $descriptor);
            if ($analysis === null) {
                return $this->stale($analysisId, $descriptor->sourceVersion, $cycleId);
            }
            if ($analysis->resummarization_status === 'processing') {
                throw LifecycleConflictException::resummarizationAlreadyActive($analysisId);
            }
            if ($analysis->processing_status !== ProcessedDocument::STATUS_COMPLETED
                || ! $analysis->hasCurrentOutput()) {
                throw LifecycleConflictException::resummarizationRequiresCompleted(
                    $analysisId,
                    $analysis->processing_status,
                );
            }

            $cycleAlreadyUsed = DocumentProcessingAttempt::query()
                ->where('processed_document_id', $analysis->id)
                ->where('cycle_id', $cycleId)
                ->lockForUpdate()
                ->exists();
            if ($cycleAlreadyUsed) {
                throw LifecycleConflictException::resummarizationCycleAlreadyUsed($analysisId, $cycleId);
            }

            $attempt = (new DocumentProcessingAttempt)->forceFill([
                'processed_document_id' => $analysis->id,
                'cycle_id' => $cycleId,
                'source_version' => $descriptor->sourceVersion,
                'operation' => 'resummarize',
                'ordinal' => 1,
                'outcome' => 'running',
                'started_at' => $this->time($startedAt),
            ]);
            $attempt->save();

            $analysis->forceFill([
                'resummarization_status' => 'processing',
                'resummarization_cycle_id' => $cycleId,
                'resummarization_error_message' => null,
                'last_resummarization_changed' => null,
            ])->save();

            return $attempt;
        });
    }

    /**
     * Publishes a non-empty summary only while the current source and summary cycle still match.
     * An empty candidate is a guarded refresh failure and never replaces the current summary.
     */
    public function completeResummarization(
        int $analysisId,
        StoredDocumentDescriptor $descriptor,
        string $cycleId,
        CandidateSummary $candidate,
        ?DateTimeInterface $finishedAt = null,
    ): ProcessedDocument|StaleCycleOutcome {
        return DB::transaction(function () use (
            $analysisId,
            $descriptor,
            $cycleId,
            $candidate,
            $finishedAt,
        ) {
            $analysis = $this->lockMatchingActiveResummarization($analysisId, $descriptor, $cycleId);
            if ($analysis === null) {
                return $this->stale($analysisId, $descriptor->sourceVersion, $cycleId);
            }

            $summary = trim($candidate->summary);
            $now = $summary === '' ? $this->time($finishedAt) : $this->positiveCompletionTime($finishedAt);
            if ($summary === '') {
                $failure = new SanitizedFailure(
                    'empty_summary',
                    'The AI service returned an empty summary. Please try again.',
                );
                if (! $this->finishResummarizationAttempt(
                    $analysis,
                    $cycleId,
                    'failed',
                    $now,
                    $failure,
                )) {
                    return $this->stale(
                        $analysisId,
                        $descriptor->sourceVersion,
                        $cycleId,
                        'attempt_not_running',
                    );
                }

                $analysis->forceFill([
                    'resummarization_status' => 'failed',
                    'resummarization_cycle_id' => null,
                    'resummarization_error_message' => $failure->reviewerMessage,
                    'last_resummarization_changed' => false,
                ])->save();

                return $analysis;
            }

            if (! $this->finishResummarizationAttempt($analysis, $cycleId, 'succeeded', $now)) {
                return $this->stale(
                    $analysisId,
                    $descriptor->sourceVersion,
                    $cycleId,
                    'attempt_not_running',
                );
            }

            $changed = $this->normalizedSummary((string) $analysis->summary)
                !== $this->normalizedSummary($summary);
            $analysis->forceFill([
                'summary' => $summary,
                'summary_version' => max(1, (int) $analysis->summary_version) + 1,
                'summary_processed_at' => $now,
                'resummarization_status' => 'idle',
                'resummarization_cycle_id' => null,
                'resummarization_error_message' => null,
                'last_resummarization_changed' => $changed,
            ])->save();

            return $analysis;
        });
    }

    /**
     * Fails only a still-current summary-refresh cycle and leaves all completed output intact.
     */
    public function failResummarization(
        int $analysisId,
        StoredDocumentDescriptor $descriptor,
        string $cycleId,
        SanitizedFailure $failure,
        ?string $providerStatusCode = null,
        ?DateTimeInterface $finishedAt = null,
    ): ProcessedDocument|StaleCycleOutcome {
        return DB::transaction(function () use (
            $analysisId,
            $descriptor,
            $cycleId,
            $failure,
            $providerStatusCode,
            $finishedAt,
        ) {
            $analysis = $this->lockMatchingActiveResummarization($analysisId, $descriptor, $cycleId);
            if ($analysis === null) {
                return $this->stale($analysisId, $descriptor->sourceVersion, $cycleId);
            }

            $now = $this->time($finishedAt);
            if (! $this->finishResummarizationAttempt(
                $analysis,
                $cycleId,
                'failed',
                $now,
                $failure,
                $providerStatusCode,
            )) {
                return $this->stale(
                    $analysisId,
                    $descriptor->sourceVersion,
                    $cycleId,
                    'attempt_not_running',
                );
            }

            $analysis->forceFill([
                'resummarization_status' => 'failed',
                'resummarization_cycle_id' => null,
                'resummarization_error_message' => $failure->reviewerMessage,
                'last_resummarization_changed' => false,
            ])->save();

            return $analysis;
        });
    }

    public function markAttemptStale(
        int $analysisId,
        string $sourceVersion,
        string $cycleId,
        int $ordinal,
        ?DateTimeInterface $finishedAt = null,
    ): bool {
        return DB::transaction(function () use ($analysisId, $sourceVersion, $cycleId, $ordinal, $finishedAt) {
            $attempt = DocumentProcessingAttempt::query()
                ->where('processed_document_id', $analysisId)
                ->where('source_version', $sourceVersion)
                ->where('cycle_id', $cycleId)
                ->where('ordinal', $ordinal)
                ->where('outcome', 'running')
                ->lockForUpdate()
                ->first();
            if ($attempt === null) {
                return false;
            }

            $now = $this->time($finishedAt);
            $attempt->forceFill([
                'outcome' => 'stale',
                'finished_at' => $now,
                'duration_ms' => $this->duration($attempt->started_at, $now),
            ])->save();

            return true;
        });
    }

    private function lockMatchingActiveProcessing(
        int $analysisId,
        StoredDocumentDescriptor $descriptor,
        string $cycleId,
    ): ?ProcessedDocument {
        $analysis = $this->lockMatchingDescriptor($analysisId, $descriptor);

        return $analysis !== null
            && $analysis->processing_status === ProcessedDocument::STATUS_PROCESSING
            && hash_equals((string) $analysis->active_cycle_id, $cycleId)
            && in_array($analysis->cycle_kind, ['initial', 'retry'], true)
            && $analysis->cycle_attempt_count >= 1
            && $analysis->cycle_attempt_count <= self::MAX_CYCLE_ATTEMPTS
                ? $analysis
                : null;
    }

    private function lockMatchingActiveResummarization(
        int $analysisId,
        StoredDocumentDescriptor $descriptor,
        string $cycleId,
    ): ?ProcessedDocument {
        $analysis = $this->lockMatchingDescriptor($analysisId, $descriptor);

        return $analysis !== null
            && $analysis->processing_status === ProcessedDocument::STATUS_COMPLETED
            && $analysis->hasCurrentOutput()
            && $analysis->resummarization_status === 'processing'
            && hash_equals((string) $analysis->resummarization_cycle_id, $cycleId)
                ? $analysis
                : null;
    }

    private function lockMatchingDescriptor(
        int $analysisId,
        StoredDocumentDescriptor $descriptor,
    ): ?ProcessedDocument {
        $application = ApplyOrg::query()
            ->whereKey($descriptor->applyOrgId)
            ->lockForUpdate()
            ->first();
        if ($application === null || ! $this->applicationMatchesDescriptor($application, $descriptor)) {
            return null;
        }

        $analysis = ProcessedDocument::query()->whereKey($analysisId)->lockForUpdate()->first();
        if ($analysis === null || ! $this->recordMatchesDescriptor($analysis, $descriptor)) {
            return null;
        }

        return $analysis;
    }

    private function isClaimable(?ProcessedDocument $analysis, string $cycleId): bool
    {
        return $analysis !== null
            && $analysis->association_ready_at !== null
            && $analysis->processing_status === ProcessedDocument::STATUS_PENDING
            && hash_equals((string) $analysis->active_cycle_id, $cycleId)
            && in_array($analysis->cycle_kind, ['initial', 'retry'], true)
            && $analysis->cycle_attempt_count >= 0
            && $analysis->cycle_attempt_count < self::MAX_CYCLE_ATTEMPTS;
    }

    private function recordMatchesDescriptor(
        ProcessedDocument $analysis,
        StoredDocumentDescriptor $descriptor,
    ): bool {
        return (int) $analysis->apply_org_id === $descriptor->applyOrgId
            && hash_equals((string) $analysis->document_field, $descriptor->field)
            && hash_equals((string) $analysis->document_slot, $descriptor->slot)
            && hash_equals((string) $analysis->original_filename, $descriptor->originalFilename)
            && hash_equals((string) $analysis->storage_disk, $descriptor->storageDisk)
            && hash_equals($this->path((string) $analysis->file_path), $this->path($descriptor->storagePath))
            && hash_equals((string) $analysis->source_version, $descriptor->sourceVersion)
            && hash_equals(strtolower((string) $analysis->source_fingerprint), strtolower($descriptor->sourceFingerprint));
    }

    private function applicationMatchesDescriptor(
        ApplyOrg $application,
        StoredDocumentDescriptor $descriptor,
    ): bool {
        if (! $this->catalog->supports($descriptor->field)
            || $this->catalog->definition($descriptor->field)->label !== $descriptor->label) {
            return false;
        }

        foreach ($this->catalog->sourcesFor($application, $descriptor->storageDisk) as $source) {
            if ($source->field !== $descriptor->field
                || $source->slot !== $descriptor->slot
                || ! hash_equals($this->path($source->storagePath), $this->path($descriptor->storagePath))
                || ! hash_equals($source->storageDisk, $descriptor->storageDisk)) {
                continue;
            }
            if ($source->sourceVersion !== null
                && ! hash_equals($source->sourceVersion, $descriptor->sourceVersion)) {
                continue;
            }
            if ($source->sourceFingerprint !== null
                && ! hash_equals(strtolower($source->sourceFingerprint), strtolower($descriptor->sourceFingerprint))) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function finishAttempt(
        ProcessedDocument $analysis,
        string $cycleId,
        string $outcome,
        Carbon $finishedAt,
        ?SanitizedFailure $failure = null,
        ?string $providerStatusCode = null,
    ): bool {
        $attempt = DocumentProcessingAttempt::query()
            ->where('processed_document_id', $analysis->id)
            ->where('cycle_id', $cycleId)
            ->where('source_version', $analysis->source_version)
            ->where('ordinal', $analysis->cycle_attempt_count)
            ->where('outcome', 'running')
            ->lockForUpdate()
            ->first();
        if ($attempt === null) {
            return false;
        }

        $attempt->forceFill([
            'outcome' => $outcome,
            'failure_class' => $failure === null ? null : $this->failureCode($failure),
            'provider_status_code' => $this->providerStatusCode($providerStatusCode),
            'sanitized_message' => $failure?->reviewerMessage,
            'finished_at' => $finishedAt,
            'duration_ms' => $this->duration($attempt->started_at, $finishedAt),
        ])->save();

        return true;
    }

    private function finishResummarizationAttempt(
        ProcessedDocument $analysis,
        string $cycleId,
        string $outcome,
        Carbon $finishedAt,
        ?SanitizedFailure $failure = null,
        ?string $providerStatusCode = null,
    ): bool {
        $attempt = DocumentProcessingAttempt::query()
            ->where('processed_document_id', $analysis->id)
            ->where('cycle_id', $cycleId)
            ->where('source_version', $analysis->source_version)
            ->where('operation', 'resummarize')
            ->where('ordinal', 1)
            ->where('outcome', 'running')
            ->lockForUpdate()
            ->first();
        if ($attempt === null) {
            return false;
        }

        $attempt->forceFill([
            'outcome' => $outcome,
            'failure_class' => $failure === null ? null : $this->failureCode($failure),
            'provider_status_code' => $this->providerStatusCode($providerStatusCode),
            'sanitized_message' => $failure?->reviewerMessage,
            'finished_at' => $finishedAt,
            'duration_ms' => $this->duration($attempt->started_at, $finishedAt),
        ])->save();

        return true;
    }

    private function time(?DateTimeInterface $time): Carbon
    {
        return $time === null ? now() : Carbon::instance($time);
    }

    private function positiveCompletionTime(?DateTimeInterface $time): Carbon
    {
        $completion = $this->time($time);

        return $completion->getTimestamp() > 0 ? $completion : now();
    }

    private function duration(DateTimeInterface|string|null $startedAt, DateTimeInterface $finishedAt): int
    {
        if ($startedAt === null) {
            return 0;
        }

        $start = $startedAt instanceof DateTimeInterface ? Carbon::instance($startedAt) : Carbon::parse($startedAt);

        return max(0, (int) round($start->diffInMilliseconds($finishedAt, false)));
    }

    private function failureCode(SanitizedFailure $failure): string
    {
        return Str::limit($failure->code, 64, '');
    }

    private function providerStatusCode(?string $status): ?string
    {
        if ($status === null || ! preg_match('/^[A-Za-z0-9_.:-]{1,64}$/', $status)) {
            return null;
        }

        return $status;
    }

    private function normalizedSummary(string $summary): string
    {
        return preg_replace('/\s+/u', ' ', trim($summary)) ?? trim($summary);
    }

    private function path(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }

    private function stale(
        int $analysisId,
        string $sourceVersion,
        string $cycleId,
        string $reason = 'source_or_cycle_mismatch',
    ): StaleCycleOutcome {
        return new StaleCycleOutcome($analysisId, $sourceVersion, $cycleId, $reason);
    }

    /** Broadcast a status change after the DB transaction has committed. */
    private function broadcastStatus(ProcessedDocument $analysis): void
    {
        $applyOrgId = (int) $analysis->apply_org_id;
        if ($applyOrgId === 0) {
            return;
        }

        event(new DocumentAnalysisStatusUpdated(
            applyOrgId:    $applyOrgId,
            analysisId:    (int) $analysis->getKey(),
            status:        (string) $analysis->processing_status,
            documentField: (string) $analysis->document_field,
            documentSlot:  (string) $analysis->document_slot,
            errorMessage:  filled($analysis->error_message) ? (string) $analysis->error_message : null,
        ));
    }
}
