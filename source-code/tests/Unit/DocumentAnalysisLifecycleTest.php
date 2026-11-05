<?php

namespace Tests\Unit;

use App\Models\ApplyOrg;
use App\Models\DocumentProcessingAttempt;
use App\Models\ProcessedDocument;
use App\Services\DocumentIntelligence\Data\CandidateSummary;
use App\Services\DocumentIntelligence\Data\ExtractedDocumentText;
use App\Services\DocumentIntelligence\Data\SanitizedFailure;
use App\Services\DocumentIntelligence\Data\ValidatedAIOutput;
use App\Services\DocumentIntelligence\DocumentAnalysisLifecycle;
use App\Services\DocumentIntelligence\Exceptions\LifecycleConflictException;
use App\Services\DocumentIntelligence\Outcomes\StaleCycleOutcome;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** **Validates: Requirements 1.3, 1.5, 2.2–2.6, 2.8, 2.9, 3.9, 5.1–5.9, 8.1, 8.2, 8.4, 8.5, 9.2, 9.5** */
final class DocumentAnalysisLifecycleTest extends TestCase
{
    private DocumentAnalysisLifecycle $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $this->lifecycle = $this->app->make(DocumentAnalysisLifecycle::class);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('document_processing_attempts');
        Schema::dropIfExists('processed_documents');
        Schema::dropIfExists('apply_orgs');
        parent::tearDown();
    }

    public function test_claim_and_completion_publish_current_output_atomically_and_cannot_regress(): void
    {
        [$analysis, $descriptor, $cycleId] = $this->pendingAnalysis();
        $started = Carbon::parse('2026-08-01 10:00:00');
        $finished = Carbon::parse('2026-08-01 10:00:02');

        $attempt = $this->lifecycle->claimPending($analysis->id, $descriptor, $cycleId, $started);
        self::assertInstanceOf(DocumentProcessingAttempt::class, $attempt);
        self::assertSame(1, $attempt->ordinal);
        self::assertSame('running', $attempt->outcome);

        $completed = $this->lifecycle->complete(
            $analysis->id,
            $descriptor,
            $cycleId,
            $this->validOutput(),
            $finished,
        );

        self::assertInstanceOf(ProcessedDocument::class, $completed);
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $completed->processing_status);
        self::assertSame($descriptor->sourceVersion, $completed->output_source_version);
        self::assertSame('A concise summary.', $completed->summary);
        self::assertSame(['constitution', 'policy'], $completed->tags);
        self::assertSame(['school_year' => '2026-2027'], $completed->key_information);
        self::assertSame('Extracted source text', $completed->extracted_text);
        self::assertTrue($completed->processed_at->isSameSecond($finished));
        self::assertGreaterThan(0, $completed->processed_at->getTimestamp());
        self::assertSame(2000, $completed->processing_duration_ms);
        self::assertNull($completed->active_cycle_id);
        self::assertNull($completed->failure_code);
        self::assertSame('succeeded', $attempt->fresh()->outcome);

        $lateFailure = $this->lifecycle->fail(
            $analysis->id,
            $descriptor,
            $cycleId,
            new SanitizedFailure('provider_unavailable', 'Please try again.'),
        );

        self::assertInstanceOf(StaleCycleOutcome::class, $lateFailure);
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $analysis->fresh()->processing_status);
        self::assertSame('A concise summary.', $analysis->fresh()->summary);
    }

    public function test_duplicate_claim_is_a_no_op_and_does_not_increment_attempt_accounting(): void
    {
        [$analysis, $descriptor, $cycleId] = $this->pendingAnalysis();

        $firstClaim = $this->lifecycle->claimPending($analysis->id, $descriptor, $cycleId);
        $duplicateClaim = $this->lifecycle->claimPending($analysis->id, $descriptor, $cycleId);

        self::assertInstanceOf(DocumentProcessingAttempt::class, $firstClaim);
        self::assertInstanceOf(StaleCycleOutcome::class, $duplicateClaim);
        self::assertSame('not_claimable', $duplicateClaim->reason);
        self::assertSame(ProcessedDocument::STATUS_PROCESSING, $analysis->fresh()->processing_status);
        self::assertSame(1, $analysis->fresh()->cycle_attempt_count);
        self::assertSame(1, DocumentProcessingAttempt::query()
            ->where('processed_document_id', $analysis->id)
            ->count());
        self::assertSame('running', $firstClaim->fresh()->outcome);
    }

    public function test_transient_failures_release_twice_then_fail_the_third_attempt(): void
    {
        [$analysis, $descriptor, $cycleId] = $this->pendingAnalysis();
        $failure = new SanitizedFailure('timed_out', 'Document processing timed out. Please try again.');

        for ($ordinal = 1; $ordinal <= 3; $ordinal++) {
            $claim = $this->lifecycle->claimPending($analysis->id, $descriptor, $cycleId);
            self::assertInstanceOf(DocumentProcessingAttempt::class, $claim);
            self::assertSame($ordinal, $claim->ordinal);

            $released = $this->lifecycle->releaseTransient(
                $analysis->id,
                $descriptor,
                $cycleId,
                $failure,
                'HTTP_503',
            );
            self::assertInstanceOf(ProcessedDocument::class, $released);
            self::assertSame(
                $ordinal < 3 ? ProcessedDocument::STATUS_PENDING : ProcessedDocument::STATUS_FAILED,
                $released->processing_status,
            );
        }

        $failed = $analysis->fresh();
        self::assertSame(3, $failed->cycle_attempt_count);
        self::assertNull($failed->active_cycle_id);
        self::assertSame('timed_out', $failed->failure_code);
        self::assertSame(3, DocumentProcessingAttempt::query()->where('processed_document_id', $analysis->id)->count());
        self::assertSame(3, DocumentProcessingAttempt::query()->where('outcome', 'failed')->count());
    }

    public function test_terminal_failure_does_not_retry_and_retry_preserves_source_details_and_review_status(): void
    {
        [$analysis, $descriptor, $cycleId] = $this->pendingAnalysis([
            'document_type' => 'legacy-type',
            'summary' => 'Retained incomplete detail',
            'tags' => ['retained'],
        ]);
        $this->lifecycle->claimPending($analysis->id, $descriptor, $cycleId);

        $failed = $this->lifecycle->fail(
            $analysis->id,
            $descriptor,
            $cycleId,
            new SanitizedFailure('document_unreadable', 'The document could not be read.'),
        );

        self::assertInstanceOf(ProcessedDocument::class, $failed);
        self::assertSame(ProcessedDocument::STATUS_FAILED, $failed->processing_status);
        self::assertSame(1, $failed->cycle_attempt_count);
        self::assertSame('legacy-type', $failed->document_type);
        self::assertSame('documents/constitution.pdf', $failed->file_path);

        $retryCycle = '22222222-2222-4222-8222-222222222222';
        $retry = $this->lifecycle->startRetry($analysis->id, $descriptor, $retryCycle);
        self::assertInstanceOf(ProcessedDocument::class, $retry);
        self::assertSame(ProcessedDocument::STATUS_PENDING, $retry->processing_status);
        self::assertSame('retry', $retry->cycle_kind);
        self::assertSame(0, $retry->cycle_attempt_count);
        self::assertSame('Retained incomplete detail', $retry->summary);
        self::assertSame(['retained'], $retry->tags);
        self::assertSame($descriptor->sourceVersion, $retry->source_version);
        self::assertSame('documents/constitution.pdf', $retry->file_path);
        self::assertSame('under_review', ApplyOrg::query()->findOrFail($descriptor->applyOrgId)->status);

        $this->expectException(LifecycleConflictException::class);
        $this->lifecycle->startRetry($analysis->id, $descriptor);
    }

    public function test_failed_retry_keeps_source_and_document_details(): void
    {
        [$analysis, $descriptor, $cycleId] = $this->pendingAnalysis([
            'processing_status' => ProcessedDocument::STATUS_FAILED,
            'active_cycle_id' => null,
            'cycle_kind' => null,
            'original_filename' => 'Board Constitution.pdf',
            'document_type_name' => 'Constitution',
            'failure_code' => 'document_missing',
            'error_message' => 'The document is unavailable.',
        ]);

        $retry = $this->lifecycle->startRetry($analysis->id, $descriptor, $cycleId);
        $this->lifecycle->claimPending($analysis->id, $descriptor, $cycleId);
        $retryFailure = $this->lifecycle->fail(
            $analysis->id,
            $descriptor,
            $cycleId,
            new SanitizedFailure('provider_unavailable', 'The AI service is temporarily unavailable.'),
        );

        self::assertSame(ProcessedDocument::STATUS_FAILED, $retryFailure->processing_status);
        self::assertSame($retry->file_path, $retryFailure->file_path);
        self::assertSame($retry->source_version, $retryFailure->source_version);
        self::assertSame('Board Constitution.pdf', $retryFailure->original_filename);
        self::assertSame('Constitution', $retryFailure->document_type_name);
        self::assertSame('under_review', ApplyOrg::query()->findOrFail($descriptor->applyOrgId)->status);
    }

    public function test_replaced_application_source_makes_completion_a_stale_no_op(): void
    {
        [$analysis, $descriptor, $cycleId] = $this->pendingAnalysis();
        $attempt = $this->lifecycle->claimPending($analysis->id, $descriptor, $cycleId);
        ApplyOrg::query()->whereKey($descriptor->applyOrgId)->update([
            'letter_of_application' => 'documents/replacement.pdf',
        ]);

        $result = $this->lifecycle->complete(
            $analysis->id,
            $descriptor,
            $cycleId,
            $this->validOutput(),
        );

        self::assertInstanceOf(StaleCycleOutcome::class, $result);
        self::assertSame(ProcessedDocument::STATUS_PROCESSING, $analysis->fresh()->processing_status);
        self::assertNull($analysis->fresh()->output_source_version);
        self::assertNull($analysis->fresh()->summary);
        self::assertTrue($this->lifecycle->markAttemptStale(
            $analysis->id,
            $descriptor->sourceVersion,
            $cycleId,
            $attempt->ordinal,
        ));
        self::assertSame('stale', $attempt->fresh()->outcome);
    }

    public function test_resummarization_requires_current_completed_output_and_rejects_duplicates_without_main_status_change(): void
    {
        [$analysis, $descriptor, $mainCycleId] = $this->pendingAnalysis();

        try {
            $this->lifecycle->startResummarization($analysis->id, $descriptor);
            self::fail('Pending analyses must not start re-summarization.');
        } catch (LifecycleConflictException) {
            self::assertSame(ProcessedDocument::STATUS_PENDING, $analysis->fresh()->processing_status);
        }

        $this->lifecycle->claimPending($analysis->id, $descriptor, $mainCycleId);
        $this->lifecycle->complete($analysis->id, $descriptor, $mainCycleId, $this->validOutput());
        $summaryCycleId = '44444444-4444-4444-8444-444444444444';
        $attempt = $this->lifecycle->startResummarization(
            $analysis->id,
            $descriptor,
            $summaryCycleId,
            Carbon::parse('2026-08-02 10:00:00'),
        );

        self::assertInstanceOf(DocumentProcessingAttempt::class, $attempt);
        self::assertSame('resummarize', $attempt->operation);
        self::assertSame(1, $attempt->ordinal);
        self::assertSame('processing', $analysis->fresh()->resummarization_status);
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $analysis->fresh()->processing_status);
        self::assertSame('A concise summary.', $analysis->fresh()->summary);

        try {
            $this->lifecycle->startResummarization($analysis->id, $descriptor);
            self::fail('An active re-summarization cycle must reject duplicates.');
        } catch (LifecycleConflictException) {
            $current = $analysis->fresh();
            self::assertSame(ProcessedDocument::STATUS_COMPLETED, $current->processing_status);
            self::assertSame('processing', $current->resummarization_status);
            self::assertSame($summaryCycleId, $current->resummarization_cycle_id);
            self::assertSame(1, DocumentProcessingAttempt::query()
                ->where('processed_document_id', $analysis->id)
                ->where('operation', 'resummarize')
                ->count());
        }
    }

    public function test_valid_resummarization_replaces_only_summary_and_records_normalized_change_result(): void
    {
        [$analysis, $descriptor, $mainCycleId] = $this->pendingAnalysis();
        $this->lifecycle->claimPending($analysis->id, $descriptor, $mainCycleId);
        $this->lifecycle->complete($analysis->id, $descriptor, $mainCycleId, $this->validOutput());
        $before = $analysis->fresh();
        $preserved = $before->only([
            'apply_org_id',
            'document_field',
            'document_slot',
            'original_filename',
            'file_path',
            'storage_disk',
            'source_version',
            'source_fingerprint',
            'output_source_version',
            'document_type',
            'document_type_name',
            'tags',
            'key_information',
            'extracted_text',
        ]);

        $changedCycle = '55555555-5555-4555-8555-555555555555';
        $changedAt = Carbon::parse('2026-08-02 11:00:00');
        $this->lifecycle->startResummarization($analysis->id, $descriptor, $changedCycle);
        $changed = $this->lifecycle->completeResummarization(
            $analysis->id,
            $descriptor,
            $changedCycle,
            new CandidateSummary('A clearer replacement summary.'),
            $changedAt,
        );

        self::assertInstanceOf(ProcessedDocument::class, $changed);
        self::assertSame('A clearer replacement summary.', $changed->summary);
        self::assertSame(2, $changed->summary_version);
        self::assertTrue($changed->summary_processed_at->isSameSecond($changedAt));
        self::assertGreaterThan(0, $changed->summary_processed_at->getTimestamp());
        self::assertTrue($changed->last_resummarization_changed);
        self::assertSame('idle', $changed->resummarization_status);
        self::assertNull($changed->resummarization_cycle_id);
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $changed->processing_status);
        self::assertSame($preserved, $changed->only(array_keys($preserved)));
        self::assertTrue($before->processed_at->isSameSecond($changed->processed_at));

        $unchangedCycle = '66666666-6666-4666-8666-666666666666';
        $this->lifecycle->startResummarization($analysis->id, $descriptor, $unchangedCycle);
        $unchanged = $this->lifecycle->completeResummarization(
            $analysis->id,
            $descriptor,
            $unchangedCycle,
            new CandidateSummary("  A clearer\n replacement   summary.  "),
        );

        self::assertSame(3, $unchanged->summary_version);
        self::assertFalse($unchanged->last_resummarization_changed);
        self::assertSame('succeeded', DocumentProcessingAttempt::query()
            ->where('cycle_id', $unchangedCycle)
            ->value('outcome'));
    }

    public function test_empty_and_failed_resummarization_preserve_output_and_allow_later_retry(): void
    {
        [$analysis, $descriptor, $mainCycleId] = $this->pendingAnalysis();
        $this->lifecycle->claimPending($analysis->id, $descriptor, $mainCycleId);
        $this->lifecycle->complete($analysis->id, $descriptor, $mainCycleId, $this->validOutput());
        $before = $analysis->fresh();
        $preserved = $before->only([
            'summary',
            'summary_version',
            'source_version',
            'file_path',
            'document_type',
            'document_type_name',
            'tags',
            'key_information',
            'extracted_text',
        ]);

        $emptyCycle = '77777777-7777-4777-8777-777777777777';
        $this->lifecycle->startResummarization($analysis->id, $descriptor, $emptyCycle);
        $empty = $this->lifecycle->completeResummarization(
            $analysis->id,
            $descriptor,
            $emptyCycle,
            new CandidateSummary(" \n\t "),
        );

        self::assertSame('failed', $empty->resummarization_status);
        self::assertNull($empty->resummarization_cycle_id);
        self::assertNotEmpty($empty->resummarization_error_message);
        self::assertFalse($empty->last_resummarization_changed);
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $empty->processing_status);
        self::assertSame($preserved, $empty->only(array_keys($preserved)));
        self::assertTrue($before->summary_processed_at->isSameSecond($empty->summary_processed_at));

        $failedCycle = '88888888-8888-4888-8888-888888888888';
        $retryAttempt = $this->lifecycle->startResummarization($analysis->id, $descriptor, $failedCycle);
        self::assertInstanceOf(DocumentProcessingAttempt::class, $retryAttempt);
        $failed = $this->lifecycle->failResummarization(
            $analysis->id,
            $descriptor,
            $failedCycle,
            new SanitizedFailure('provider_unavailable', 'The AI service is temporarily unavailable.'),
            'HTTP_503',
        );

        self::assertSame('failed', $failed->resummarization_status);
        self::assertSame('The AI service is temporarily unavailable.', $failed->resummarization_error_message);
        self::assertSame($preserved, $failed->only(array_keys($preserved)));
        self::assertTrue($before->summary_processed_at->isSameSecond($failed->summary_processed_at));
        self::assertSame('failed', $retryAttempt->fresh()->outcome);
        self::assertSame('provider_unavailable', $retryAttempt->fresh()->failure_class);
        self::assertSame('HTTP_503', $retryAttempt->fresh()->provider_status_code);

        $laterRetry = $this->lifecycle->startResummarization(
            $analysis->id,
            $descriptor,
            '99999999-9999-4999-8999-999999999999',
        );
        self::assertInstanceOf(DocumentProcessingAttempt::class, $laterRetry);
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $analysis->fresh()->processing_status);
        self::assertSame('processing', $analysis->fresh()->resummarization_status);
        self::assertNull($analysis->fresh()->resummarization_error_message);
    }

    public function test_wrong_or_stale_resummarization_source_and_cycle_are_no_ops(): void
    {
        [$analysis, $descriptor, $mainCycleId] = $this->pendingAnalysis();
        $this->lifecycle->claimPending($analysis->id, $descriptor, $mainCycleId);
        $this->lifecycle->complete($analysis->id, $descriptor, $mainCycleId, $this->validOutput());
        $summaryCycle = 'aaaaaaaa-1111-4111-8111-111111111111';
        $attempt = $this->lifecycle->startResummarization($analysis->id, $descriptor, $summaryCycle);
        $before = $analysis->fresh();

        $wrongCycle = $this->lifecycle->completeResummarization(
            $analysis->id,
            $descriptor,
            'bbbbbbbb-1111-4111-8111-111111111111',
            new CandidateSummary('Must not be stored.'),
        );
        self::assertInstanceOf(StaleCycleOutcome::class, $wrongCycle);
        self::assertSame($before->summary, $analysis->fresh()->summary);
        self::assertSame($before->summary_version, $analysis->fresh()->summary_version);
        self::assertSame('running', $attempt->fresh()->outcome);

        ApplyOrg::query()->whereKey($descriptor->applyOrgId)->update([
            'letter_of_application' => 'documents/replacement.pdf',
        ]);
        $staleSource = $this->lifecycle->failResummarization(
            $analysis->id,
            $descriptor,
            $summaryCycle,
            new SanitizedFailure('provider_unavailable', 'Please try again.'),
        );

        self::assertInstanceOf(StaleCycleOutcome::class, $staleSource);
        self::assertSame($before->summary, $analysis->fresh()->summary);
        self::assertSame($before->summary_version, $analysis->fresh()->summary_version);
        self::assertSame($before->document_type, $analysis->fresh()->document_type);
        self::assertSame($before->tags, $analysis->fresh()->tags);
        self::assertSame($before->key_information, $analysis->fresh()->key_information);
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $analysis->fresh()->processing_status);
    }

    public function test_wrong_cycle_release_completion_and_failure_are_stale_no_ops(): void
    {
        [$analysis, $descriptor, $cycleId] = $this->pendingAnalysis();
        $attempt = $this->lifecycle->claimPending($analysis->id, $descriptor, $cycleId);
        $wrongCycleId = '33333333-3333-4333-8333-333333333333';
        $failure = new SanitizedFailure('timed_out', 'Document processing timed out. Please try again.');

        $release = $this->lifecycle->releaseTransient(
            $analysis->id,
            $descriptor,
            $wrongCycleId,
            $failure,
        );
        $completion = $this->lifecycle->complete(
            $analysis->id,
            $descriptor,
            $wrongCycleId,
            $this->validOutput(),
        );
        $terminalFailure = $this->lifecycle->fail(
            $analysis->id,
            $descriptor,
            $wrongCycleId,
            $failure,
        );

        self::assertInstanceOf(StaleCycleOutcome::class, $release);
        self::assertInstanceOf(StaleCycleOutcome::class, $completion);
        self::assertInstanceOf(StaleCycleOutcome::class, $terminalFailure);
        self::assertSame(ProcessedDocument::STATUS_PROCESSING, $analysis->fresh()->processing_status);
        self::assertSame($cycleId, $analysis->fresh()->active_cycle_id);
        self::assertSame(1, $analysis->fresh()->cycle_attempt_count);
        self::assertNull($analysis->fresh()->output_source_version);
        self::assertNull($analysis->fresh()->failure_code);
        self::assertSame('running', $attempt->fresh()->outcome);
        self::assertFalse($this->lifecycle->markAttemptStale(
            $analysis->id,
            $descriptor->sourceVersion,
            $wrongCycleId,
            $attempt->ordinal,
        ));
    }

    /** @return array{ProcessedDocument, StoredDocumentDescriptor, string} */
    private function pendingAnalysis(array $overrides = []): array
    {
        $cycleId = '11111111-1111-4111-8111-111111111111';
        $sourceVersion = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $fingerprint = str_repeat('b', 64);
        $applicationId = DB::table('apply_orgs')->insertGetId([
            'letter_of_application' => 'documents/constitution.pdf',
            'status' => 'under_review',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $attributes = array_merge([
            'apply_org_id' => $applicationId,
            'document_field' => 'letter_of_application',
            'document_slot' => 'primary',
            'original_filename' => 'constitution.pdf',
            'file_path' => 'documents/constitution.pdf',
            'storage_disk' => 'private',
            'source_version' => $sourceVersion,
            'source_fingerprint' => $fingerprint,
            'association_ready_at' => now(),
            'active_cycle_id' => $cycleId,
            'cycle_kind' => 'initial',
            'cycle_attempt_count' => 0,
            'processing_status' => ProcessedDocument::STATUS_PENDING,
            'summary_version' => 0,
            'resummarization_status' => 'idle',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
        foreach (['tags', 'key_information'] as $jsonColumn) {
            if (isset($attributes[$jsonColumn]) && is_array($attributes[$jsonColumn])) {
                $attributes[$jsonColumn] = json_encode($attributes[$jsonColumn], JSON_THROW_ON_ERROR);
            }
        }
        $analysisId = DB::table('processed_documents')->insertGetId($attributes);
        $analysis = ProcessedDocument::query()->findOrFail($analysisId);
        $descriptor = new StoredDocumentDescriptor(
            $applicationId,
            'letter_of_application',
            'primary',
            'Letter of Application',
            $analysis->original_filename,
            'private',
            'documents/constitution.pdf',
            $sourceVersion,
            $fingerprint,
        );

        return [$analysis, $descriptor, $cycleId];
    }

    private function validOutput(): ValidatedAIOutput
    {
        return new ValidatedAIOutput(
            new ExtractedDocumentText('Extracted source text', 'application/pdf', 'pdf'),
            'constitution',
            'Constitution',
            'A concise summary.',
            ['school_year' => '2026-2027'],
            ['constitution', 'policy'],
        );
    }

    private function createSchema(): void
    {
        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('letter_of_application')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('processed_documents', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('apply_org_id');
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('storage_disk')->nullable();
            $table->string('source_version')->nullable();
            $table->string('source_fingerprint', 64)->nullable();
            $table->timestamp('association_ready_at')->nullable();
            $table->string('active_cycle_id')->nullable();
            $table->string('cycle_kind')->nullable();
            $table->unsignedTinyInteger('cycle_attempt_count')->default(0);
            $table->timestamp('processing_started_at')->nullable();
            $table->unsignedBigInteger('processing_duration_ms')->nullable();
            $table->string('output_source_version')->nullable();
            $table->string('failure_code')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_type_name')->nullable();
            $table->text('summary')->nullable();
            $table->json('tags')->nullable();
            $table->json('key_information')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('processing_status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('summary_version')->default(0);
            $table->timestamp('summary_processed_at')->nullable();
            $table->string('resummarization_status')->default('idle');
            $table->string('resummarization_cycle_id')->nullable();
            $table->text('resummarization_error_message')->nullable();
            $table->boolean('last_resummarization_changed')->nullable();
            $table->timestamps();
        });

        Schema::create('document_processing_attempts', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('processed_document_id');
            $table->string('cycle_id');
            $table->string('source_version');
            $table->string('operation');
            $table->unsignedTinyInteger('ordinal');
            $table->string('outcome')->default('running');
            $table->string('failure_class')->nullable();
            $table->string('provider_status_code')->nullable();
            $table->text('sanitized_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->timestamps();
            $table->unique(['processed_document_id', 'cycle_id', 'ordinal']);
        });
    }
}
