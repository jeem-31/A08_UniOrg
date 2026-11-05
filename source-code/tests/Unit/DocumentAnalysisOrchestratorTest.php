<?php

namespace Tests\Unit;

use App\Jobs\ProcessDocumentAnalysis;
use App\Jobs\ReSummarizeDocument;
use App\Models\ProcessedDocument;
use App\Services\DocumentIntelligence\AIOutputValidator;
use App\Services\DocumentIntelligence\Contracts\DocumentIntelligenceProvider;
use App\Services\DocumentIntelligence\Contracts\SourceDocumentReader;
use App\Services\DocumentIntelligence\Contracts\SourceTextExtractor;
use App\Services\DocumentIntelligence\Data\CandidateAIOutput;
use App\Services\DocumentIntelligence\Data\CandidateSummary;
use App\Services\DocumentIntelligence\Data\ExtractedDocumentText;
use App\Services\DocumentIntelligence\Data\ReadOnlyDocumentSource;
use App\Services\DocumentIntelligence\DocumentAnalysisLifecycle;
use App\Services\DocumentIntelligence\DocumentAnalysisOrchestrator;
use App\Services\DocumentIntelligence\Exceptions\PermanentDocumentException;
use App\Services\DocumentIntelligence\Exceptions\TransientProcessingException;
use App\Services\DocumentIntelligence\FailureSanitizer;
use App\Services\DocumentIntelligence\LocalTemporaryDocumentSource;
use App\Services\DocumentIntelligence\Outcomes\PermanentDocumentFailureReason;
use App\Services\DocumentIntelligence\Outcomes\StaleCycleOutcome;
use App\Services\DocumentIntelligence\Outcomes\TransientProcessingFailureReason;
use App\Services\DocumentIntelligence\ReSummarizationOrchestrator;
use App\Services\DocumentIntelligence\StorageSourceDocumentReader;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;
use App\Services\DocumentIntelligence\SupportedDocumentCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

/** **Validates: Requirements 2.2–2.5, 2.8, 3.1–3.6, 3.9–3.11, 5.1–5.3, 6.1–6.13, 7.3–7.4, 8.1, 8.3, 9.2** */
final class DocumentAnalysisOrchestratorTest extends TestCase
{
    private string $diskRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        $this->diskRoot = storage_path('framework/testing/disks/orchestrator');
        File::deleteDirectory($this->diskRoot);
        config()->set('filesystems.disks.orchestrator-test', [
            'driver' => 'local',
            'root' => $this->diskRoot,
            'throw' => false,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->diskRoot);
        Schema::dropIfExists('document_processing_attempts');
        Schema::dropIfExists('processed_documents');
        Schema::dropIfExists('apply_orgs');
        parent::tearDown();
    }

    public function test_full_processing_job_uses_scalar_async_configuration_and_non_releasing_analysis_lock(): void
    {
        config()->set('queue.default', 'redis');

        $job = new ProcessDocumentAnalysis(
            42,
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            '11111111-1111-4111-8111-111111111111',
        );
        $middleware = $job->middleware();

        self::assertSame(42, $job->analysisId);
        self::assertSame('redis', $job->connection);
        self::assertSame('document-ai', $job->queue);
        self::assertTrue($job->afterCommit);
        self::assertSame(3, $job->tries);
        self::assertSame(1, $job->maxExceptions);
        self::assertTrue($job->failOnTimeout);
        self::assertCount(1, $middleware);
        self::assertSame('42', $middleware[0]->key);
        self::assertSame('document-analysis:', $middleware[0]->prefix);
        self::assertNull($middleware[0]->releaseAfter);
    }

    public function test_resummarization_job_uses_scalar_async_configuration_and_shared_analysis_lock(): void
    {
        config()->set('queue.default', 'redis');

        $job = new ReSummarizeDocument(
            42,
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            '22222222-2222-4222-8222-222222222222',
        );
        $middleware = $job->middleware();

        self::assertSame(42, $job->analysisId);
        self::assertSame('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', $job->sourceVersion);
        self::assertSame('22222222-2222-4222-8222-222222222222', $job->summaryCycleId);
        self::assertSame('redis', $job->connection);
        self::assertSame('document-ai', $job->queue);
        self::assertTrue($job->afterCommit);
        self::assertSame(3, $job->tries);
        self::assertSame([15, 60], $job->backoff);
        self::assertNull($job->maxExceptions);
        self::assertTrue($job->failOnTimeout);
        self::assertCount(1, $middleware);
        self::assertSame('42', $middleware[0]->key);
        self::assertSame('document-analysis:', $middleware[0]->prefix);
        self::assertNull($middleware[0]->releaseAfter);
    }

    public function test_resummarization_uses_retained_current_text_and_duplicate_deliveries_are_harmless(): void
    {
        [$analysis, $summaryCycleId] = $this->refreshingAnalysis(true);
        $extractor = new OrchestratorTextExtractor(new \RuntimeException('fallback extraction must not run'));
        $provider = new OrchestratorIntelligenceProvider(
            $this->validCandidate(new ExtractedDocumentText('unused')),
            new CandidateSummary('A clearer retained-text summary.'),
        );
        [$orchestrator, $reader] = $this->resummarizationOrchestrator($extractor, $provider);
        $this->app->instance(ReSummarizationOrchestrator::class, $orchestrator);
        $job = new ReSummarizeDocument($analysis->id, $analysis->source_version, $summaryCycleId);

        $job->handle($orchestrator);

        $completed = $analysis->fresh();
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $completed->processing_status);
        self::assertSame('idle', $completed->resummarization_status);
        self::assertSame('A clearer retained-text summary.', $completed->summary);
        self::assertSame(2, $completed->summary_version);
        self::assertTrue($completed->last_resummarization_changed);
        self::assertSame(1, $provider->summarizeCalls);
        self::assertSame('Source-derived document text.', $provider->summarizedText?->text);
        self::assertNull($reader->temporaryPath);
        $this->assertDatabaseHas('document_processing_attempts', [
            'processed_document_id' => $analysis->id,
            'cycle_id' => $summaryCycleId,
            'operation' => 'resummarize',
            'outcome' => 'succeeded',
        ]);

        $job->handle($orchestrator);
        $job->failed(new \RuntimeException('late secret provider failure'));

        self::assertSame(1, $provider->summarizeCalls);
        self::assertSame('A clearer retained-text summary.', $analysis->fresh()->summary);
        self::assertSame('idle', $analysis->fresh()->resummarization_status);
        self::assertSame('unchanged-source-bytes', Storage::disk('orchestrator-test')->get('documents/source.pdf'));
    }

    public function test_resummarization_falls_back_to_read_only_extraction_and_cleans_temporary_files(): void
    {
        [$analysis, $summaryCycleId] = $this->refreshingAnalysis(false);
        $extractor = new OrchestratorTextExtractor(new ExtractedDocumentText(
            'Fallback source-derived text.',
            'application/pdf',
            'test-fallback',
        ));
        $provider = new OrchestratorIntelligenceProvider(
            $this->validCandidate(new ExtractedDocumentText('unused')),
            new CandidateSummary('Fallback extraction summary.'),
        );
        [$orchestrator, $reader] = $this->resummarizationOrchestrator($extractor, $provider);

        (new ReSummarizeDocument($analysis->id, $analysis->source_version, $summaryCycleId))
            ->handle($orchestrator);

        self::assertSame('Fallback extraction summary.', $analysis->fresh()->summary);
        self::assertSame('Fallback source-derived text.', $provider->summarizedText?->text);
        self::assertNotNull($reader->temporaryPath);
        self::assertFileDoesNotExist($reader->temporaryPath);
        self::assertSame('unchanged-source-bytes', Storage::disk('orchestrator-test')->get('documents/source.pdf'));
        self::assertSame('under_review', DB::table('apply_orgs')->where('id', $analysis->apply_org_id)->value('status'));
    }

    public function test_resummarization_sanitizes_provider_failure_and_preserves_completed_output(): void
    {
        [$analysis, $summaryCycleId] = $this->refreshingAnalysis(true);
        $provider = new OrchestratorIntelligenceProvider(
            $this->validCandidate(new ExtractedDocumentText('unused')),
            new \RuntimeException('Bearer secret-token raw provider response'),
        );
        [$orchestrator] = $this->resummarizationOrchestrator(
            new OrchestratorTextExtractor(new ExtractedDocumentText('unused')),
            $provider,
        );

        $result = $orchestrator->refresh($analysis->id, $analysis->source_version, $summaryCycleId);

        self::assertInstanceOf(ProcessedDocument::class, $result);
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $result->processing_status);
        self::assertSame('failed', $result->resummarization_status);
        self::assertSame('A useful source-derived summary.', $result->summary);
        self::assertFalse($result->last_resummarization_changed);
        self::assertStringNotContainsString('secret-token', $result->resummarization_error_message);
        $this->assertDatabaseHas('document_processing_attempts', [
            'processed_document_id' => $analysis->id,
            'cycle_id' => $summaryCycleId,
            'outcome' => 'failed',
            'failure_class' => 'unexpected_failure',
        ]);
    }

    public function test_resummarization_transient_failure_is_rethrown_for_retry_and_keeps_cycle_processing(): void
    {
        [$analysis, $summaryCycleId] = $this->refreshingAnalysis(true);
        $provider = new OrchestratorIntelligenceProvider(
            $this->validCandidate(new ExtractedDocumentText('unused')),
            new TransientProcessingException(TransientProcessingFailureReason::RateLimited),
        );
        [$orchestrator] = $this->resummarizationOrchestrator(
            new OrchestratorTextExtractor(new ExtractedDocumentText('unused')),
            $provider,
        );

        try {
            $orchestrator->refresh($analysis->id, $analysis->source_version, $summaryCycleId);
            self::fail('Transient failures must be rethrown so the queue can retry with backoff.');
        } catch (TransientProcessingException $failure) {
            self::assertSame(TransientProcessingFailureReason::RateLimited, $failure->reason);
        }

        $current = $analysis->fresh();
        self::assertSame('processing', $current->resummarization_status);
        self::assertSame($summaryCycleId, $current->resummarization_cycle_id);
        self::assertNull($current->resummarization_error_message);
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $current->processing_status);
        self::assertSame('A useful source-derived summary.', $current->summary);

        $this->assertDatabaseMissing('document_processing_attempts', [
            'processed_document_id' => $analysis->id,
            'cycle_id' => $summaryCycleId,
            'outcome' => 'failed',
        ]);
    }

    public function test_resummarization_persistence_failure_rolls_back_candidate_preserves_metadata_and_allows_retry(): void
    {
        [$analysis, $summaryCycleId] = $this->refreshingAnalysis(true);
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
            'summary',
            'summary_version',
        ]);
        $provider = new OrchestratorIntelligenceProvider(
            $this->validCandidate(new ExtractedDocumentText('unused')),
            new CandidateSummary('Candidate that must roll back.'),
        );
        [$orchestrator] = $this->resummarizationOrchestrator(
            new OrchestratorTextExtractor(new ExtractedDocumentText('unused')),
            $provider,
        );

        ProcessedDocument::saving(function (ProcessedDocument $document): void {
            if ($document->isDirty('summary')
                && $document->summary === 'Candidate that must roll back.') {
                throw new \RuntimeException(
                    'Persistence rejected Bearer secret-token and raw provider response.',
                );
            }
        });

        try {
            $result = $orchestrator->refresh(
                $analysis->id,
                $analysis->source_version,
                $summaryCycleId,
            );
        } finally {
            ProcessedDocument::flushEventListeners();
            ProcessedDocument::clearBootedModels();
        }

        self::assertInstanceOf(ProcessedDocument::class, $result);
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $result->processing_status);
        self::assertSame('failed', $result->resummarization_status);
        self::assertFalse($result->last_resummarization_changed);
        self::assertSame($preserved, $result->only(array_keys($preserved)));
        self::assertTrue($before->summary_processed_at->equalTo($result->summary_processed_at));
        self::assertTrue($before->processed_at->equalTo($result->processed_at));
        self::assertStringNotContainsString('secret-token', $result->resummarization_error_message);
        self::assertStringNotContainsString('raw provider response', $result->resummarization_error_message);
        self::assertSame(
            'documents/source.pdf',
            DB::table('apply_orgs')->where('id', $analysis->apply_org_id)->value('letter_of_application'),
        );
        self::assertSame(
            'under_review',
            DB::table('apply_orgs')->where('id', $analysis->apply_org_id)->value('status'),
        );
        self::assertSame(
            'unchanged-source-bytes',
            Storage::disk('orchestrator-test')->get('documents/source.pdf'),
        );
        $attempt = DB::table('document_processing_attempts')
            ->where('processed_document_id', $analysis->id)
            ->where('cycle_id', $summaryCycleId)
            ->first();
        self::assertSame('failed', $attempt->outcome);
        self::assertSame('unexpected_failure', $attempt->failure_class);
        self::assertStringNotContainsString('secret-token', (string) $attempt->sanitized_message);

        $retryCycleId = '44444444-4444-4444-8444-444444444444';
        $retry = (new DocumentAnalysisLifecycle(new SupportedDocumentCatalog))
            ->startResummarization(
                $analysis->id,
                $this->descriptorFor($analysis->fresh(), new SupportedDocumentCatalog),
                $retryCycleId,
            );
        self::assertSame('running', $retry->outcome);
        self::assertSame('processing', $analysis->fresh()->resummarization_status);
        self::assertSame($retryCycleId, $analysis->fresh()->resummarization_cycle_id);
    }

    public function test_it_completes_valid_output_and_cleans_only_the_temporary_copy(): void
    {
        [$analysis, $cycleId] = $this->pendingAnalysis();
        $extractor = new OrchestratorTextExtractor(new ExtractedDocumentText(
            'Source-derived document text.',
            'application/pdf',
            'test',
        ));
        $provider = new OrchestratorIntelligenceProvider($this->validCandidate($extractor->result));
        [$orchestrator, $reader] = $this->orchestrator($extractor, $provider);

        $result = $orchestrator->process($analysis->id, $analysis->source_version, $cycleId);

        self::assertInstanceOf(ProcessedDocument::class, $result);
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $result->processing_status);
        self::assertSame('A useful source-derived summary.', $result->summary);
        self::assertSame($analysis->source_version, $result->output_source_version);
        self::assertNotNull($result->processed_at);
        self::assertSame('unchanged-source-bytes', Storage::disk('orchestrator-test')->get('documents/source.pdf'));
        self::assertNotNull($reader->temporaryPath);
        self::assertFileDoesNotExist($reader->temporaryPath);
        $this->assertDatabaseHas('document_processing_attempts', [
            'processed_document_id' => $analysis->id,
            'ordinal' => 1,
            'outcome' => 'succeeded',
        ]);
    }

    public function test_it_stops_before_analysis_and_permanently_fails_when_extraction_has_no_text(): void
    {
        [$analysis, $cycleId] = $this->pendingAnalysis();
        $extractor = new OrchestratorTextExtractor(new ExtractedDocumentText(" \n\t "));
        $provider = new OrchestratorIntelligenceProvider($this->validCandidate($extractor->result));
        [$orchestrator, $reader] = $this->orchestrator($extractor, $provider);

        $result = $orchestrator->process($analysis->id, $analysis->source_version, $cycleId);

        self::assertSame(ProcessedDocument::STATUS_FAILED, $result->processing_status);
        self::assertSame('document_empty', $result->failure_code);
        self::assertSame(0, $provider->analyzeCalls);
        self::assertNull($result->summary);
        self::assertFileDoesNotExist($reader->temporaryPath);
        $this->assertDatabaseHas('document_processing_attempts', [
            'processed_document_id' => $analysis->id,
            'outcome' => 'failed',
            'failure_class' => 'document_empty',
        ]);
    }

    public function test_job_releases_only_lifecycle_authorized_transient_attempts_and_ignores_duplicates(): void
    {
        [$analysis, $cycleId] = $this->pendingAnalysis();
        $text = new ExtractedDocumentText('Readable source text.');
        $extractor = new OrchestratorTextExtractor($text);
        $provider = new OrchestratorIntelligenceProvider(new TransientProcessingException(
            TransientProcessingFailureReason::RateLimited,
            'secret provider response',
        ));
        [$orchestrator] = $this->orchestrator($extractor, $provider);

        $first = (new ProcessDocumentAnalysis($analysis->id, $analysis->source_version, $cycleId))
            ->withFakeQueueInteractions();
        $first->handle($orchestrator);
        $first->assertReleased();

        $second = (new ProcessDocumentAnalysis($analysis->id, $analysis->source_version, $cycleId))
            ->withFakeQueueInteractions();
        $second->handle($orchestrator);
        $second->assertReleased();

        $third = (new ProcessDocumentAnalysis($analysis->id, $analysis->source_version, $cycleId))
            ->withFakeQueueInteractions();
        $third->handle($orchestrator);
        $third->assertNotReleased();

        $duplicate = (new ProcessDocumentAnalysis($analysis->id, $analysis->source_version, $cycleId))
            ->withFakeQueueInteractions();
        $duplicate->handle($orchestrator);
        $duplicate->assertNotReleased();

        $failed = $analysis->fresh();
        self::assertSame(ProcessedDocument::STATUS_FAILED, $failed->processing_status);
        self::assertSame(3, $failed->cycle_attempt_count);
        self::assertSame('rate_limited', $failed->failure_code);
        self::assertStringNotContainsString('secret', $failed->error_message);
        self::assertSame(3, DB::table('document_processing_attempts')->count());
        self::assertSame('unchanged-source-bytes', Storage::disk('orchestrator-test')->get('documents/source.pdf'));
    }

    public function test_terminal_hook_sanitizes_failure_and_only_fails_the_matching_active_cycle(): void
    {
        [$analysis, $cycleId] = $this->pendingAnalysis();
        $text = new ExtractedDocumentText('Readable source text.');
        [$orchestrator] = $this->orchestrator(
            new OrchestratorTextExtractor($text),
            new OrchestratorIntelligenceProvider($this->validCandidate($text)),
        );
        $this->app->instance(DocumentAnalysisOrchestrator::class, $orchestrator);

        (new ProcessDocumentAnalysis($analysis->id, $analysis->source_version, $cycleId))
            ->failed(new \RuntimeException('Bearer secret-token raw provider body'));

        $failed = $analysis->fresh();
        self::assertSame(ProcessedDocument::STATUS_FAILED, $failed->processing_status);
        self::assertSame('unexpected_failure', $failed->failure_code);
        self::assertStringNotContainsString('secret-token', $failed->error_message);
        self::assertNull($failed->active_cycle_id);

        [$staleTarget] = $this->pendingAnalysis();
        (new ProcessDocumentAnalysis(
            $staleTarget->id,
            $staleTarget->source_version,
            '22222222-2222-4222-8222-222222222222',
        ))->failed(new \RuntimeException('must not affect the active cycle'));

        self::assertSame(ProcessedDocument::STATUS_PENDING, $staleTarget->fresh()->processing_status);
        self::assertNotNull($staleTarget->fresh()->active_cycle_id);
    }

    public function test_invalid_output_fails_without_publishing_and_preserves_source_and_review_state(): void
    {
        [$analysis, $cycleId] = $this->pendingAnalysis();
        $text = new ExtractedDocumentText('Readable source text.');
        $invalidCandidate = new CandidateAIOutput(
            $text,
            'letter_of_application',
            'Letter of Application',
            '   ',
            ['organization' => 'Test Organization'],
            ['Letter of Application'],
        );
        [$orchestrator, $reader] = $this->orchestrator(
            new OrchestratorTextExtractor($text),
            new OrchestratorIntelligenceProvider($invalidCandidate),
        );

        $result = $orchestrator->process($analysis->id, $analysis->source_version, $cycleId);

        self::assertSame(ProcessedDocument::STATUS_FAILED, $result->processing_status);
        self::assertSame('empty_summary', $result->failure_code);
        self::assertNull($result->summary);
        self::assertNull($result->output_source_version);
        self::assertFileDoesNotExist($reader->temporaryPath);
        self::assertSame('unchanged-source-bytes', Storage::disk('orchestrator-test')->get('documents/source.pdf'));
        self::assertSame('documents/source.pdf', DB::table('apply_orgs')->where('id', $analysis->apply_org_id)->value('letter_of_application'));
        self::assertSame('under_review', DB::table('apply_orgs')->where('id', $analysis->apply_org_id)->value('status'));
        $this->assertDatabaseHas('document_processing_attempts', [
            'processed_document_id' => $analysis->id,
            'outcome' => 'failed',
            'failure_class' => 'empty_summary',
        ]);
    }

    public function test_transient_release_can_succeed_and_duplicate_delivery_cannot_republish(): void
    {
        [$analysis, $cycleId] = $this->pendingAnalysis();
        $text = new ExtractedDocumentText('Readable source text.');
        $candidate = $this->validCandidate($text);
        $provider = new class($candidate) implements DocumentIntelligenceProvider
        {
            public int $analyzeCalls = 0;

            public function __construct(private readonly CandidateAIOutput $candidate) {}

            public function analyze(ExtractedDocumentText $text): CandidateAIOutput
            {
                $this->analyzeCalls++;
                if ($this->analyzeCalls === 1) {
                    throw new TransientProcessingException(
                        TransientProcessingFailureReason::ProviderUnavailable,
                        'temporary raw provider response',
                    );
                }

                return $this->candidate;
            }

            public function summarize(ExtractedDocumentText $text): CandidateSummary
            {
                return new CandidateSummary('Summary');
            }
        };
        [$orchestrator] = $this->orchestrator(new OrchestratorTextExtractor($text), $provider);

        $first = (new ProcessDocumentAnalysis($analysis->id, $analysis->source_version, $cycleId))
            ->withFakeQueueInteractions();
        $first->handle($orchestrator);
        $first->assertReleased();

        $second = (new ProcessDocumentAnalysis($analysis->id, $analysis->source_version, $cycleId))
            ->withFakeQueueInteractions();
        $second->handle($orchestrator);
        $second->assertNotReleased();

        $completed = $analysis->fresh();
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $completed->processing_status);
        self::assertSame('A useful source-derived summary.', $completed->summary);
        self::assertSame(2, $completed->cycle_attempt_count);
        self::assertSame(2, $provider->analyzeCalls);
        self::assertSame(2, DB::table('document_processing_attempts')->count());

        $duplicate = (new ProcessDocumentAnalysis($analysis->id, $analysis->source_version, $cycleId))
            ->withFakeQueueInteractions();
        $duplicate->handle($orchestrator);
        $duplicate->assertNotReleased();

        self::assertSame(2, $provider->analyzeCalls);
        self::assertSame(2, DB::table('document_processing_attempts')->count());
        self::assertSame('A useful source-derived summary.', $analysis->fresh()->summary);
        self::assertSame('unchanged-source-bytes', Storage::disk('orchestrator-test')->get('documents/source.pdf'));
        self::assertSame('under_review', DB::table('apply_orgs')->where('id', $analysis->apply_org_id)->value('status'));
    }

    public function test_permanent_source_failure_is_immediate_and_not_released(): void
    {
        [$analysis, $cycleId] = $this->pendingAnalysis();
        $text = new ExtractedDocumentText('Unused text.');
        $provider = new OrchestratorIntelligenceProvider($this->validCandidate($text));
        [$orchestrator, $reader] = $this->orchestrator(
            new OrchestratorTextExtractor(new PermanentDocumentException(
                PermanentDocumentFailureReason::Unreadable,
            )),
            $provider,
        );
        $job = (new ProcessDocumentAnalysis($analysis->id, $analysis->source_version, $cycleId))
            ->withFakeQueueInteractions();

        $job->handle($orchestrator);

        $job->assertNotReleased();
        $failed = $analysis->fresh();
        self::assertSame(ProcessedDocument::STATUS_FAILED, $failed->processing_status);
        self::assertSame('document_unreadable', $failed->failure_code);
        self::assertSame(1, $failed->cycle_attempt_count);
        self::assertSame(0, $provider->analyzeCalls);
        self::assertSame(1, DB::table('document_processing_attempts')->count());
        self::assertFileDoesNotExist($reader->temporaryPath);
        self::assertSame('unchanged-source-bytes', Storage::disk('orchestrator-test')->get('documents/source.pdf'));
        self::assertSame('under_review', DB::table('apply_orgs')->where('id', $analysis->apply_org_id)->value('status'));
    }

    public function test_source_replacement_during_analysis_makes_old_publication_stale_and_cleans_temporary_copy(): void
    {
        [$analysis, $cycleId] = $this->pendingAnalysis();
        $text = new ExtractedDocumentText('Readable text from the old source.');
        $candidate = $this->validCandidate($text);
        $replacementSourceVersion = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
        $replacementCycleId = '22222222-2222-4222-8222-222222222222';
        Storage::disk('orchestrator-test')->put('documents/replacement.pdf', 'replacement-source-bytes');
        $provider = new class($candidate, $analysis->id, $analysis->apply_org_id, $replacementSourceVersion, $replacementCycleId) implements DocumentIntelligenceProvider
        {
            public function __construct(
                private readonly CandidateAIOutput $candidate,
                private readonly int $analysisId,
                private readonly int $applicationId,
                private readonly string $replacementSourceVersion,
                private readonly string $replacementCycleId,
            ) {}

            public function analyze(ExtractedDocumentText $text): CandidateAIOutput
            {
                DB::table('apply_orgs')->where('id', $this->applicationId)->update([
                    'letter_of_application' => 'documents/replacement.pdf',
                    'updated_at' => now(),
                ]);
                DB::table('processed_documents')->where('id', $this->analysisId)->update([
                    'original_filename' => 'replacement.pdf',
                    'file_path' => 'documents/replacement.pdf',
                    'source_version' => $this->replacementSourceVersion,
                    'source_fingerprint' => hash('sha256', 'replacement-source-bytes'),
                    'active_cycle_id' => $this->replacementCycleId,
                    'cycle_kind' => 'initial',
                    'cycle_attempt_count' => 0,
                    'processing_status' => ProcessedDocument::STATUS_PENDING,
                    'processing_started_at' => null,
                    'output_source_version' => null,
                    'updated_at' => now(),
                ]);

                return $this->candidate;
            }

            public function summarize(ExtractedDocumentText $text): CandidateSummary
            {
                return new CandidateSummary('Summary');
            }
        };
        [$orchestrator, $reader] = $this->orchestrator(new OrchestratorTextExtractor($text), $provider);

        $result = $orchestrator->process($analysis->id, $analysis->source_version, $cycleId);

        self::assertInstanceOf(StaleCycleOutcome::class, $result);
        $replacement = $analysis->fresh();
        self::assertSame(ProcessedDocument::STATUS_PENDING, $replacement->processing_status);
        self::assertSame($replacementSourceVersion, $replacement->source_version);
        self::assertSame($replacementCycleId, $replacement->active_cycle_id);
        self::assertNull($replacement->summary);
        self::assertNull($replacement->output_source_version);
        self::assertFileDoesNotExist($reader->temporaryPath);
        self::assertSame('stale', DB::table('document_processing_attempts')->where('processed_document_id', $analysis->id)->value('outcome'));
        self::assertSame('unchanged-source-bytes', Storage::disk('orchestrator-test')->get('documents/source.pdf'));
        self::assertSame('replacement-source-bytes', Storage::disk('orchestrator-test')->get('documents/replacement.pdf'));
        self::assertSame('under_review', DB::table('apply_orgs')->where('id', $analysis->apply_org_id)->value('status'));
    }

    public function test_job_failure_reported_after_completion_cannot_regress_persisted_success(): void
    {
        [$analysis, $cycleId] = $this->pendingAnalysis();
        $text = new ExtractedDocumentText('Readable source text.');
        [$orchestrator] = $this->orchestrator(
            new OrchestratorTextExtractor($text),
            new OrchestratorIntelligenceProvider($this->validCandidate($text)),
        );
        $this->app->instance(DocumentAnalysisOrchestrator::class, $orchestrator);
        $job = (new ProcessDocumentAnalysis($analysis->id, $analysis->source_version, $cycleId))
            ->withFakeQueueInteractions();

        $job->handle($orchestrator);
        $job->failed(new \RuntimeException('late queue completion persistence failure'));

        $completed = $analysis->fresh();
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $completed->processing_status);
        self::assertSame($analysis->source_version, $completed->output_source_version);
        self::assertSame('A useful source-derived summary.', $completed->summary);
        self::assertNotNull($completed->processed_at);
        self::assertNull($completed->failure_code);
        self::assertSame(1, DB::table('document_processing_attempts')->count());
        self::assertSame('succeeded', DB::table('document_processing_attempts')->value('outcome'));
        self::assertSame('under_review', DB::table('apply_orgs')->where('id', $analysis->apply_org_id)->value('status'));
    }

    /** @return array{ProcessedDocument, string} */
    private function pendingAnalysis(): array
    {
        $cycleId = '11111111-1111-4111-8111-111111111111';
        $sourceVersion = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $fingerprint = hash('sha256', 'unchanged-source-bytes');
        Storage::disk('orchestrator-test')->put('documents/source.pdf', 'unchanged-source-bytes');
        $applicationId = DB::table('apply_orgs')->insertGetId([
            'letter_of_application' => 'documents/source.pdf',
            'status' => 'under_review',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $analysisId = DB::table('processed_documents')->insertGetId([
            'apply_org_id' => $applicationId,
            'document_field' => 'letter_of_application',
            'document_slot' => 'primary',
            'original_filename' => 'source.pdf',
            'file_path' => 'documents/source.pdf',
            'storage_disk' => 'orchestrator-test',
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
        ]);

        return [ProcessedDocument::query()->findOrFail($analysisId), $cycleId];
    }

    private function validCandidate(ExtractedDocumentText $text): CandidateAIOutput
    {
        return new CandidateAIOutput(
            $text,
            'letter_of_application',
            'Letter of Application',
            'A useful source-derived summary.',
            ['organization' => 'Test Organization'],
            ['application', 'Letter of Application'],
        );
    }

    /** @return array{DocumentAnalysisOrchestrator, CapturingSourceReader} */
    private function orchestrator(
        SourceTextExtractor $extractor,
        DocumentIntelligenceProvider $provider,
    ): array {
        $catalog = new SupportedDocumentCatalog;
        $reader = new CapturingSourceReader(new StorageSourceDocumentReader);
        $orchestrator = new DocumentAnalysisOrchestrator(
            new DocumentAnalysisLifecycle($catalog),
            $catalog,
            $reader,
            $extractor,
            $provider,
            new AIOutputValidator,
            new FailureSanitizer,
        );

        return [$orchestrator, $reader];
    }

    /** @return array{ProcessedDocument, string} */
    private function refreshingAnalysis(bool $retainExtractedText): array
    {
        [$analysis, $mainCycleId] = $this->pendingAnalysis();
        $catalog = new SupportedDocumentCatalog;
        $lifecycle = new DocumentAnalysisLifecycle($catalog);
        $descriptor = $this->descriptorFor($analysis, $catalog);
        $text = new ExtractedDocumentText('Source-derived document text.', 'application/pdf', 'test');

        $lifecycle->claimPending($analysis->id, $descriptor, $mainCycleId);
        $lifecycle->complete(
            $analysis->id,
            $descriptor,
            $mainCycleId,
            (new AIOutputValidator)->validate($this->validCandidate($text)),
        );
        $analysis = $analysis->fresh();
        if (! $retainExtractedText) {
            $analysis->forceFill(['extracted_text' => null])->save();
        }

        $summaryCycleId = '33333333-3333-4333-8333-333333333333';
        $lifecycle->startResummarization(
            $analysis->id,
            $this->descriptorFor($analysis->fresh(), $catalog),
            $summaryCycleId,
        );

        return [$analysis->fresh(), $summaryCycleId];
    }

    private function descriptorFor(
        ProcessedDocument $analysis,
        SupportedDocumentCatalog $catalog,
    ): StoredDocumentDescriptor {
        return new StoredDocumentDescriptor(
            (int) $analysis->apply_org_id,
            (string) $analysis->document_field,
            (string) $analysis->document_slot,
            $catalog->definition((string) $analysis->document_field)->label,
            (string) $analysis->original_filename,
            (string) $analysis->storage_disk,
            (string) $analysis->file_path,
            (string) $analysis->source_version,
            (string) $analysis->source_fingerprint,
        );
    }

    /** @return array{ReSummarizationOrchestrator, CapturingSourceReader} */
    private function resummarizationOrchestrator(
        SourceTextExtractor $extractor,
        DocumentIntelligenceProvider $provider,
    ): array {
        $catalog = new SupportedDocumentCatalog;
        $reader = new CapturingSourceReader(new StorageSourceDocumentReader);
        $orchestrator = new ReSummarizationOrchestrator(
            new DocumentAnalysisLifecycle($catalog),
            $catalog,
            $reader,
            $extractor,
            $provider,
            new FailureSanitizer,
        );

        return [$orchestrator, $reader];
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

final class CapturingSourceReader implements SourceDocumentReader
{
    public ?string $temporaryPath = null;

    public function __construct(private readonly SourceDocumentReader $reader) {}

    public function open(StoredDocumentDescriptor $source): ReadOnlyDocumentSource
    {
        $opened = $this->reader->open($source);
        if ($opened instanceof LocalTemporaryDocumentSource) {
            $this->temporaryPath = $opened->localPath();
        }

        return $opened;
    }
}

final class OrchestratorTextExtractor implements SourceTextExtractor
{
    public function __construct(public readonly ExtractedDocumentText|Throwable $result) {}

    public function extract(ReadOnlyDocumentSource $source): ExtractedDocumentText
    {
        if ($this->result instanceof Throwable) {
            throw $this->result;
        }

        return $this->result;
    }
}

final class OrchestratorIntelligenceProvider implements DocumentIntelligenceProvider
{
    public int $analyzeCalls = 0;

    public int $summarizeCalls = 0;

    public ?ExtractedDocumentText $summarizedText = null;

    public function __construct(
        private readonly CandidateAIOutput|Throwable $result,
        private readonly CandidateSummary|Throwable|null $summaryResult = null,
    ) {}

    public function analyze(ExtractedDocumentText $text): CandidateAIOutput
    {
        $this->analyzeCalls++;
        if ($this->result instanceof Throwable) {
            throw $this->result;
        }

        return $this->result;
    }

    public function summarize(ExtractedDocumentText $text): CandidateSummary
    {
        $this->summarizeCalls++;
        $this->summarizedText = $text;
        if ($this->summaryResult instanceof Throwable) {
            throw $this->summaryResult;
        }

        return $this->summaryResult ?? new CandidateSummary('Summary');
    }
}
