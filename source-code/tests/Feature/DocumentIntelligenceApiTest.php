<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentAnalysis;
use App\Jobs\ReSummarizeDocument;
use App\Models\ApplyOrg;
use App\Models\ProcessedDocument;
use App\Models\User;
use App\Services\DocumentIntelligence\Data\CandidateSummary;
use App\Services\DocumentIntelligence\Data\ExtractedDocumentText;
use App\Services\DocumentIntelligence\Data\SanitizedFailure;
use App\Services\DocumentIntelligence\Data\ValidatedAIOutput;
use App\Services\DocumentIntelligence\DocumentAnalysisLifecycle;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** **Validates: Requirements 2.7, 4.3–4.5, 4.7–4.10, 5.5–5.9, 6.1–6.13, 7.2–7.5, 8.2–8.3, 9.1** */
final class DocumentIntelligenceApiTest extends TestCase
{
    private const DISK = 'document-api-test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        Storage::fake(self::DISK);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS pg_temp.document_processing_attempts, pg_temp.processed_documents, pg_temp.apply_orgs, pg_temp.users CASCADE');
        } else {
            Schema::dropIfExists('document_processing_attempts');
            Schema::dropIfExists('processed_documents');
            Schema::dropIfExists('apply_orgs');
            Schema::dropIfExists('users');
        }

        parent::tearDown();
    }

    public function test_unauthenticated_and_non_osas_requests_leak_no_protected_data(): void
    {
        $application = $this->application();
        $analysis = $this->analysis($application, ProcessedDocument::STATUS_FAILED, [
            'summary' => 'TOP-SECRET-SUMMARY',
            'extracted_text' => 'TOP-SECRET-EXTRACTED-TEXT',
            'key_information' => ['token' => 'TOP-SECRET-CONTEXT'],
            'tags' => ['TOP-SECRET-TAG'],
            'error_message' => 'SAFE-FAILURE-FOR-OSAS-ONLY',
        ]);
        Storage::disk(self::DISK)->put($analysis->file_path, 'TOP-SECRET-SOURCE-BYTES');

        $requests = [
            ['getJson', 'osas.apply-org.analyses.status'],
            ['getJson', 'osas.apply-org.analyses.source'],
            ['getJson', 'osas.apply-org.analyses.summary'],
            ['getJson', 'osas.apply-org.analyses.details'],
            ['postJson', 'osas.apply-org.analyses.retry'],
            ['postJson', 'osas.apply-org.analyses.resummarize'],
        ];
        $secrets = [
            'TOP-SECRET-SUMMARY', 'TOP-SECRET-EXTRACTED-TEXT', 'TOP-SECRET-CONTEXT',
            'TOP-SECRET-TAG', 'SAFE-FAILURE-FOR-OSAS-ONLY', 'TOP-SECRET-SOURCE-BYTES',
            $analysis->file_path,
        ];

        foreach ($requests as [$method, $route]) {
            $response = $this->{$method}(route($route, [$application, $analysis]));
            $response->assertUnauthorized();
            $this->assertResponseOmits($response->getContent(), $secrets);
        }

        $this->actingAs($this->user('student'));
        foreach ($requests as [$method, $route]) {
            $response = $this->{$method}(route($route, [$application, $analysis]));
            $response->assertForbidden();
            $this->assertResponseOmits($response->getContent(), $secrets);
        }
        Queue::assertNothingPushed();
    }

    public function test_cross_application_and_stale_source_requests_are_denied_without_leakage(): void
    {
        $application = $this->application();
        $otherApplication = $this->application('documents/other.pdf');
        $analysis = $this->analysis($application, ProcessedDocument::STATUS_COMPLETED, [
            'summary' => 'CROSS-APPLICATION-SECRET',
            'error_message' => 'PRIVATE-ERROR',
        ]);
        $this->actingAs($this->user('osas_admin'));

        $crossApplication = $this->getJson(route(
            'osas.apply-org.analyses.summary',
            [$otherApplication, $analysis],
        ));
        $crossApplication->assertNotFound();
        $this->assertResponseOmits($crossApplication->getContent(), ['CROSS-APPLICATION-SECRET', 'PRIVATE-ERROR']);

        $application->forceFill(['letter_of_application' => 'documents/replacement.pdf'])->save();
        $stale = $this->getJson(route('osas.apply-org.analyses.summary', [$application, $analysis]));
        $stale->assertForbidden();
        $this->assertResponseOmits($stale->getContent(), [
            'CROSS-APPLICATION-SECRET', 'PRIVATE-ERROR', $analysis->file_path,
        ]);
        Queue::assertNothingPushed();
    }

    public function test_osas_admin_can_stream_the_current_source_inline(): void
    {
        $application = $this->application();
        $analysis = $this->analysis($application, ProcessedDocument::STATUS_PENDING, [
            'original_filename' => 'Board Plan.pdf',
        ]);
        Storage::disk(self::DISK)->put($analysis->file_path, 'unchanged-source-bytes');

        $response = $this->actingAs($this->user('osas_admin'))->get(
            route('osas.apply-org.analyses.source', [$application, $analysis]),
        );

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString("filename*=utf-8''Board%20Plan.pdf", (string) $response->headers->get('Content-Disposition'));
        self::assertSame('unchanged-source-bytes', $response->streamedContent());
        Queue::assertNothingPushed();
    }

    public function test_status_summary_and_details_are_shaped_by_persisted_state_and_gets_never_dispatch(): void
    {
        $this->actingAs($this->user('osas_admin'));

        foreach ([ProcessedDocument::STATUS_PENDING, ProcessedDocument::STATUS_PROCESSING] as $status) {
            $application = $this->application("documents/{$status}.pdf");
            $analysis = $this->analysis($application, $status, [
                'summary' => 'HIDDEN-ACTIVE-SUMMARY',
                'tags' => ['HIDDEN-ACTIVE-TAG'],
                'key_information' => ['secret' => 'HIDDEN-ACTIVE-CONTEXT'],
            ]);

            $this->getJson(route('osas.apply-org.analyses.status', [$application, $analysis]))
                ->assertOk()
                ->assertExactJson([
                    'analysis_id' => $analysis->id,
                    'status' => $status,
                    'output_available' => false,
                    'updated_at' => $analysis->updated_at?->toIso8601String(),
                ]);
            foreach (['summary', 'details'] as $surface) {
                $response = $this->getJson(route("osas.apply-org.analyses.{$surface}", [$application, $analysis]));
                $response->assertConflict()->assertExactJson([
                    'message' => 'Generated output is not available for this document.',
                    'status' => $status,
                ]);
                $this->assertResponseOmits($response->getContent(), [
                    'HIDDEN-ACTIVE-SUMMARY', 'HIDDEN-ACTIVE-TAG', 'HIDDEN-ACTIVE-CONTEXT',
                ]);
            }
        }

        $failedApplication = $this->application('documents/failed.pdf');
        $failed = $this->analysis($failedApplication, ProcessedDocument::STATUS_FAILED, [
            'error_message' => 'The document could not be read. Please upload a readable file or retry.',
            'summary' => 'HIDDEN-FAILED-SUMMARY',
        ]);
        $this->getJson(route('osas.apply-org.analyses.status', [$failedApplication, $failed]))
            ->assertOk()
            ->assertExactJson([
                'analysis_id' => $failed->id,
                'status' => ProcessedDocument::STATUS_FAILED,
                'output_available' => false,
                'failure_message' => 'The document could not be read. Please upload a readable file or retry.',
                'updated_at' => $failed->updated_at?->toIso8601String(),
            ]);
        foreach (['summary', 'details'] as $surface) {
            $response = $this->getJson(route("osas.apply-org.analyses.{$surface}", [$failedApplication, $failed]));
            $response->assertConflict();
            self::assertStringNotContainsString('HIDDEN-FAILED-SUMMARY', $response->getContent());
        }

        $completedApplication = $this->application('documents/completed.pdf');
        $completed = $this->analysis($completedApplication, ProcessedDocument::STATUS_COMPLETED);
        $refreshMetadata = [
            'status' => 'idle',
            'in_progress' => false,
            'failure_message' => null,
            'last_result_changed' => null,
            'summary_version' => 1,
            'summary_processed_at' => $completed->summary_processed_at?->toIso8601String(),
        ];
        $this->getJson(route('osas.apply-org.analyses.status', [$completedApplication, $completed]))
            ->assertOk()
            ->assertExactJson([
                'analysis_id' => $completed->id,
                'status' => ProcessedDocument::STATUS_COMPLETED,
                'output_available' => true,
                'updated_at' => $completed->updated_at?->toIso8601String(),
                'resummarization' => $refreshMetadata,
            ]);
        $this->getJson(route('osas.apply-org.analyses.summary', [$completedApplication, $completed]))
            ->assertOk()
            ->assertExactJson([
                'analysis_id' => $completed->id,
                'ai_generated' => true,
                'advisory' => '',
                'summary' => 'Persisted current-source summary.',
                'context' => ['school_year' => '2026-2027'],
                'resummarization' => $refreshMetadata,
            ]);
        $this->getJson(route('osas.apply-org.analyses.details', [$completedApplication, $completed]))
            ->assertOk()
            ->assertJsonPath('requirement_name', 'Letter of Application')
            ->assertJsonPath('original_filename', 'Original Letter.pdf')
            ->assertJsonPath('detected_document_type', 'Application Letter')
            ->assertJsonPath('status', ProcessedDocument::STATUS_COMPLETED)
            ->assertJsonPath('tags', ['application letter', 'request'])
            ->assertJsonPath('processing_duration_ms', 125)
            ->assertJsonPath('ai_generated', true);

        Queue::assertNothingPushed();
    }

    public function test_failed_only_retry_is_claimed_transactionally_and_dispatched_after_commit(): void
    {
        config()->set('queue.default', 'redis');

        $application = $this->application();
        $analysis = $this->analysis($application, ProcessedDocument::STATUS_FAILED, [
            'document_type' => 'legacy_type',
            'document_type_name' => 'Legacy Type',
            'tags' => ['legacy'],
            'key_information' => ['year' => '2025'],
            'error_message' => 'Previous safe failure.',
            'failure_code' => 'source_unreadable',
        ]);
        Storage::disk(self::DISK)->put($analysis->file_path, 'retry-source-bytes');
        $sourceBefore = $this->sourceSnapshot($analysis);

        $response = $this->actingAs($this->user('osas_admin'))->postJson(
            route('osas.apply-org.analyses.retry', [$application, $analysis]),
        );
        $response->assertAccepted()->assertJsonPath('status', ProcessedDocument::STATUS_PENDING);
        $claimed = $analysis->fresh();

        Queue::assertPushed(ProcessDocumentAnalysis::class, 1);
        Queue::assertPushed(ProcessDocumentAnalysis::class, fn (ProcessDocumentAnalysis $job): bool => $job->analysisId === $analysis->id
            && $job->sourceVersion === $analysis->source_version
            && $job->cycleId === $claimed->active_cycle_id
            && $job->connection === 'redis'
            && $job->queue === 'document-ai'
            && $job->afterCommit === true);
        self::assertSame($sourceBefore, $this->sourceSnapshot($claimed->fresh()));
        self::assertSame('Under Review', $application->fresh()->status);
        self::assertSame('retry-source-bytes', Storage::disk(self::DISK)->get($analysis->file_path));
        self::assertSame('Legacy Type', $claimed->document_type_name);
        self::assertSame(['legacy'], $claimed->tags);
        self::assertNull($claimed->error_message);
        self::assertNull($claimed->failure_code);
    }

    public function test_duplicate_and_non_failed_retry_requests_return_conflict_without_extra_dispatch(): void
    {
        $this->actingAs($this->user('osas_admin'));
        $application = $this->application();
        $failed = $this->analysis($application, ProcessedDocument::STATUS_FAILED);
        $retryUrl = route('osas.apply-org.analyses.retry', [$application, $failed]);

        $this->postJson($retryUrl)->assertAccepted();
        $this->postJson($retryUrl)->assertConflict()->assertJsonPath(
            'message',
            'Document processing is already active or is not eligible for retry.',
        );

        foreach ([ProcessedDocument::STATUS_PROCESSING, ProcessedDocument::STATUS_COMPLETED] as $status) {
            $otherApplication = $this->application("documents/{$status}-conflict.pdf");
            $active = $this->analysis($otherApplication, $status);
            $this->postJson(route('osas.apply-org.analyses.retry', [$otherApplication, $active]))
                ->assertConflict();
        }
        Queue::assertPushed(ProcessDocumentAnalysis::class, 1);
    }

    public function test_retry_success_and_failure_preserve_source_application_and_document_details(): void
    {
        $this->actingAs($this->user('osas_admin'));
        $lifecycle = app(DocumentAnalysisLifecycle::class);

        $successApplication = $this->application('documents/retry-success.pdf');
        $successful = $this->analysis($successApplication, ProcessedDocument::STATUS_FAILED, [
            'original_filename' => 'Success Source.pdf',
            'error_message' => 'Previous failure.',
        ]);
        Storage::disk(self::DISK)->put($successful->file_path, 'success-source-bytes');
        $successSource = $this->sourceSnapshot($successful);
        $this->postJson(route('osas.apply-org.analyses.retry', [$successApplication, $successful]))
            ->assertAccepted();
        $successful = $successful->fresh();
        $successDescriptor = $this->descriptor($successApplication, $successful);
        $attempt = $lifecycle->claimPending(
            $successful->id,
            $successDescriptor,
            $successful->active_cycle_id,
        );
        self::assertNotNull($attempt);
        $lifecycle->complete(
            $successful->id,
            $successDescriptor,
            $successful->active_cycle_id,
            new ValidatedAIOutput(
                new ExtractedDocumentText('Current extracted text.', 'application/pdf', 'pdf'),
                'application_letter',
                'Application Letter',
                'Retry produced a current summary.',
                ['organization' => 'Example Society'],
                ['application letter', 'request'],
            ),
        );

        $successful = $successful->fresh();
        self::assertSame($successSource, $this->sourceSnapshot($successful));
        self::assertSame('success-source-bytes', Storage::disk(self::DISK)->get($successful->file_path));
        self::assertSame('Under Review', $successApplication->fresh()->status);
        $this->getJson(route('osas.apply-org.analyses.details', [$successApplication, $successful]))
            ->assertOk()
            ->assertJsonPath('original_filename', 'Success Source.pdf')
            ->assertJsonPath('detected_document_type', 'Application Letter')
            ->assertJsonPath('status', ProcessedDocument::STATUS_COMPLETED);
        $this->getJson(route('osas.apply-org.analyses.summary', [$successApplication, $successful]))
            ->assertOk()
            ->assertJsonPath('summary', 'Retry produced a current summary.');

        $failureApplication = $this->application('documents/retry-failure.pdf');
        $failed = $this->analysis($failureApplication, ProcessedDocument::STATUS_FAILED, [
            'original_filename' => 'Failure Source.pdf',
            'document_type' => 'preserved_type',
            'document_type_name' => 'Preserved Type',
            'summary' => 'Preserved incomplete detail.',
            'tags' => ['preserved'],
            'key_information' => ['preserved' => true],
            'error_message' => 'Earlier safe failure.',
        ]);
        Storage::disk(self::DISK)->put($failed->file_path, 'failure-source-bytes');
        $failureSource = $this->sourceSnapshot($failed);
        $this->postJson(route('osas.apply-org.analyses.retry', [$failureApplication, $failed]))
            ->assertAccepted();
        $failed = $failed->fresh();
        $failureDescriptor = $this->descriptor($failureApplication, $failed);
        $lifecycle->claimPending($failed->id, $failureDescriptor, $failed->active_cycle_id);
        $lifecycle->fail(
            $failed->id,
            $failureDescriptor,
            $failed->active_cycle_id,
            new SanitizedFailure('provider_unavailable', 'The AI service is temporarily unavailable. Please retry.'),
        );

        $failed = $failed->fresh();
        self::assertSame($failureSource, $this->sourceSnapshot($failed));
        self::assertSame('failure-source-bytes', Storage::disk(self::DISK)->get($failed->file_path));
        self::assertSame('Under Review', $failureApplication->fresh()->status);
        self::assertSame('Preserved Type', $failed->document_type_name);
        self::assertSame('Preserved incomplete detail.', $failed->summary);
        self::assertSame(['preserved'], $failed->tags);
        self::assertSame(['preserved' => true], $failed->key_information);
        $this->getJson(route('osas.apply-org.analyses.status', [$failureApplication, $failed]))
            ->assertOk()
            ->assertJsonPath('failure_message', 'The AI service is temporarily unavailable. Please retry.');
        $this->getJson(route('osas.apply-org.analyses.details', [$failureApplication, $failed]))
            ->assertConflict()
            ->assertJsonMissing(['detected_document_type' => 'Preserved Type']);
        $sourceResponse = $this->get(route('osas.apply-org.analyses.source', [$failureApplication, $failed]));
        $sourceResponse->assertOk();
        self::assertSame('failure-source-bytes', $sourceResponse->streamedContent());
    }

    public function test_resummarization_claims_dispatches_and_exposes_persisted_result_metadata(): void
    {
        config()->set('queue.default', 'redis');

        $application = $this->application('documents/resummarize.pdf');
        $analysis = $this->analysis($application, ProcessedDocument::STATUS_COMPLETED);
        Storage::disk(self::DISK)->put($analysis->file_path, 'unchanged-resummarization-source');
        $sourceBefore = $this->sourceSnapshot($analysis);
        $url = route('osas.apply-org.analyses.resummarize', [$application, $analysis]);

        $response = $this->actingAs($this->user('osas_admin'))->postJson($url);
        $response->assertAccepted()
            ->assertJsonPath('status', ProcessedDocument::STATUS_COMPLETED)
            ->assertJsonPath('resummarization.status', 'processing')
            ->assertJsonPath('resummarization.in_progress', true);

        $active = $analysis->fresh();
        Queue::assertPushed(ReSummarizeDocument::class, 1);
        Queue::assertPushed(ReSummarizeDocument::class, fn (ReSummarizeDocument $job): bool => $job->analysisId === $analysis->id
            && $job->sourceVersion === $analysis->source_version
            && $job->summaryCycleId === $active->resummarization_cycle_id
            && $job->connection === 'redis'
            && $job->queue === 'document-ai'
            && $job->afterCommit === true);
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $active->processing_status);
        self::assertSame('Persisted current-source summary.', $active->summary);
        $this->assertDatabaseHas('document_processing_attempts', [
            'processed_document_id' => $analysis->id,
            'cycle_id' => $active->resummarization_cycle_id,
            'operation' => 'resummarize',
            'outcome' => 'running',
        ]);

        $this->getJson(route('osas.apply-org.analyses.summary', [$application, $analysis]))
            ->assertOk()
            ->assertJsonPath('summary', 'Persisted current-source summary.')
            ->assertJsonPath('resummarization.status', 'processing')
            ->assertJsonPath('resummarization.in_progress', true);
        $this->postJson($url)->assertConflict()->assertJsonPath(
            'message',
            'Document re-summarization is already active or is not eligible.',
        );
        Queue::assertPushed(ReSummarizeDocument::class, 1);

        $lifecycle = app(DocumentAnalysisLifecycle::class);
        $completed = $lifecycle->completeResummarization(
            $analysis->id,
            $this->descriptor($application, $active),
            $active->resummarization_cycle_id,
            new CandidateSummary('A clearer persisted summary.'),
        );
        self::assertInstanceOf(ProcessedDocument::class, $completed);
        $this->getJson(route('osas.apply-org.analyses.status', [$application, $analysis]))
            ->assertOk()
            ->assertJsonPath('resummarization.status', 'idle')
            ->assertJsonPath('resummarization.in_progress', false)
            ->assertJsonPath('resummarization.last_result_changed', true)
            ->assertJsonPath('resummarization.summary_version', 2);
        $this->getJson(route('osas.apply-org.analyses.summary', [$application, $analysis]))
            ->assertOk()
            ->assertJsonPath('summary', 'A clearer persisted summary.');

        $this->postJson($url)->assertAccepted();
        $failedCycle = $analysis->fresh()->resummarization_cycle_id;
        $lifecycle->failResummarization(
            $analysis->id,
            $this->descriptor($application, $analysis->fresh()),
            $failedCycle,
            new SanitizedFailure('provider_unavailable', 'The AI service is temporarily unavailable.'),
        );
        $this->getJson(route('osas.apply-org.analyses.status', [$application, $analysis]))
            ->assertOk()
            ->assertJsonPath('resummarization.status', 'failed')
            ->assertJsonPath('resummarization.failure_message', 'The AI service is temporarily unavailable.')
            ->assertJsonPath('resummarization.last_result_changed', false);
        $this->getJson(route('osas.apply-org.analyses.summary', [$application, $analysis]))
            ->assertOk()
            ->assertJsonPath('summary', 'A clearer persisted summary.');

        self::assertSame($sourceBefore, $this->sourceSnapshot($analysis->fresh()));
        self::assertSame('unchanged-resummarization-source', Storage::disk(self::DISK)->get($analysis->file_path));
        self::assertSame('Under Review', $application->fresh()->status);
    }

    public function test_resummarization_endpoint_rejects_non_completed_and_stale_sources_without_dispatch(): void
    {
        $this->actingAs($this->user('osas_admin'));

        foreach ([
            ProcessedDocument::STATUS_PENDING,
            ProcessedDocument::STATUS_PROCESSING,
            ProcessedDocument::STATUS_FAILED,
        ] as $status) {
            $application = $this->application("documents/resummarize-{$status}.pdf");
            $analysis = $this->analysis($application, $status);

            $this->postJson(route(
                'osas.apply-org.analyses.resummarize',
                [$application, $analysis],
            ))->assertConflict()->assertJsonPath(
                'message',
                'Document re-summarization is already active or is not eligible.',
            );

            $current = $analysis->fresh();
            self::assertSame($status, $current->processing_status);
            self::assertSame('idle', $current->resummarization_status);
            self::assertNull($current->resummarization_cycle_id);
            self::assertSame('Persisted current-source summary.', $current->summary);
        }

        $application = $this->application('documents/resummarize-stale.pdf');
        $analysis = $this->analysis($application, ProcessedDocument::STATUS_COMPLETED);
        $application->forceFill([
            'letter_of_application' => 'documents/resummarize-replacement.pdf',
        ])->save();

        $response = $this->postJson(route(
            'osas.apply-org.analyses.resummarize',
            [$application, $analysis],
        ));
        $response->assertForbidden();
        self::assertStringNotContainsString('Persisted current-source summary.', $response->getContent());
        self::assertStringNotContainsString('Protected current-source extracted text.', $response->getContent());
        self::assertSame('idle', $analysis->fresh()->resummarization_status);

        Queue::assertNothingPushed();
    }

    private function user(string $role): User
    {
        static $ordinal = 0;
        $ordinal++;

        $user = (new User)->forceFill([
            'name' => "API User {$ordinal}",
            'email' => "document-api-{$ordinal}@example.test",
            'password' => 'not-used',
            'role' => $role,
            'is_active' => true,
        ]);
        $user->save();

        return $user;
    }

    private function application(string $path = 'documents/current.pdf'): ApplyOrg
    {
        $application = (new ApplyOrg)->forceFill([
            'letter_of_application' => $path,
            'status' => 'Under Review',
        ]);
        $application->save();

        return $application;
    }

    /** @param array<string, mixed> $overrides */
    private function analysis(ApplyOrg $application, string $status, array $overrides = []): ProcessedDocument
    {
        $sourceVersion = (string) Str::uuid();
        $active = in_array($status, [ProcessedDocument::STATUS_PENDING, ProcessedDocument::STATUS_PROCESSING], true);
        $attributes = [
            'apply_org_id' => $application->id,
            'document_field' => 'letter_of_application',
            'document_slot' => 'primary',
            'original_filename' => 'Original Letter.pdf',
            'file_path' => $application->letter_of_application,
            'storage_disk' => self::DISK,
            'source_version' => $sourceVersion,
            'source_fingerprint' => hash('sha256', "source-{$sourceVersion}"),
            'association_ready_at' => now(),
            'active_cycle_id' => $active ? (string) Str::uuid() : null,
            'cycle_kind' => $active ? 'initial' : null,
            'cycle_attempt_count' => $status === ProcessedDocument::STATUS_PROCESSING ? 1 : 0,
            'processing_started_at' => $status === ProcessedDocument::STATUS_PROCESSING ? now()->subSecond() : null,
            'processing_duration_ms' => $status === ProcessedDocument::STATUS_COMPLETED ? 125 : null,
            'output_source_version' => $status === ProcessedDocument::STATUS_COMPLETED ? $sourceVersion : null,
            'failure_code' => $status === ProcessedDocument::STATUS_FAILED ? 'source_unreadable' : null,
            'document_type' => 'application_letter',
            'document_type_name' => 'Application Letter',
            'summary' => 'Persisted current-source summary.',
            'tags' => ['application letter', 'request'],
            'key_information' => ['school_year' => '2026-2027'],
            'extracted_text' => 'Protected current-source extracted text.',
            'processing_status' => $status,
            'error_message' => $status === ProcessedDocument::STATUS_FAILED ? 'Document processing failed safely.' : null,
            'processed_at' => $status === ProcessedDocument::STATUS_COMPLETED ? now() : null,
            'summary_version' => $status === ProcessedDocument::STATUS_COMPLETED ? 1 : 0,
            'summary_processed_at' => $status === ProcessedDocument::STATUS_COMPLETED ? now() : null,
            'resummarization_status' => 'idle',
        ];

        $analysis = (new ProcessedDocument)->forceFill(array_replace($attributes, $overrides));
        $analysis->save();

        return $analysis;
    }

    private function descriptor(ApplyOrg $application, ProcessedDocument $analysis): StoredDocumentDescriptor
    {
        return new StoredDocumentDescriptor(
            $application->id,
            $analysis->document_field,
            $analysis->document_slot,
            'Letter of Application',
            $analysis->original_filename,
            $analysis->storage_disk,
            $analysis->file_path,
            $analysis->source_version,
            $analysis->source_fingerprint,
        );
    }

    /** @return array<string, mixed> */
    private function sourceSnapshot(ProcessedDocument $analysis): array
    {
        return [
            'apply_org_id' => $analysis->apply_org_id,
            'document_field' => $analysis->document_field,
            'document_slot' => $analysis->document_slot,
            'original_filename' => $analysis->original_filename,
            'file_path' => $analysis->file_path,
            'storage_disk' => $analysis->storage_disk,
            'source_version' => $analysis->source_version,
            'source_fingerprint' => $analysis->source_fingerprint,
        ];
    }

    /** @param list<string> $secrets */
    private function assertResponseOmits(string $content, array $secrets): void
    {
        foreach ($secrets as $secret) {
            self::assertStringNotContainsString($secret, $content);
        }
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

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
            $table->unique(['apply_org_id', 'document_field', 'document_slot']);
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
