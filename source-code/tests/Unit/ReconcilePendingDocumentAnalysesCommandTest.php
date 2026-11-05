<?php

namespace Tests\Unit;

use App\Jobs\ProcessDocumentAnalysis;
use App\Models\DocumentProcessingAttempt;
use App\Models\ProcessedDocument;
use App\Models\User;
use App\Services\DocumentIntelligence\DocumentAnalysisLifecycle;
use App\Services\DocumentIntelligence\Outcomes\StaleCycleOutcome;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/** **Validates: Requirements 1.3–1.5, 4.9, 9.2** */
final class ReconcilePendingDocumentAnalysesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('document_processing_attempts');
        Schema::dropIfExists('processed_documents');
        Schema::dropIfExists('apply_orgs');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_it_leaves_association_incomplete_rows_pending_and_dispatches_ready_rows_with_exact_identity(): void
    {
        config()->set('queue.default', 'redis');

        $ready = $this->analysis('pending', now(), 'source-current', 'cycle-current');
        $incomplete = $this->analysis('pending', null, 'source-incomplete', 'cycle-incomplete');
        $this->analysis('processing', now(), 'source-active', 'cycle-active');

        $this->artisan('documents:reconcile-pending', ['--limit' => 10])
            ->expectsOutput('Dispatched 1 pending document analysis job(s).')
            ->assertSuccessful();

        self::assertSame(ProcessedDocument::STATUS_PENDING, $incomplete->fresh()->processing_status);
        self::assertNull($incomplete->fresh()->association_ready_at);
        Queue::assertNotPushed(
            ProcessDocumentAnalysis::class,
            fn (ProcessDocumentAnalysis $job): bool => $job->analysisId === $incomplete->id,
        );
        Queue::assertPushed(ProcessDocumentAnalysis::class, 1);
        Queue::assertPushed(ProcessDocumentAnalysis::class, fn (ProcessDocumentAnalysis $job): bool => $job->analysisId === $ready->id
            && $job->sourceVersion === 'source-current'
            && $job->cycleId === 'cycle-current'
            && $job->connection === 'redis'
            && $job->queue === 'document-ai'
        );
    }

    public function test_duplicate_reconciler_deliveries_cannot_start_another_active_cycle(): void
    {
        [$analysis, $descriptor] = $this->readyAssociatedAnalysis();

        $this->artisan('documents:reconcile-pending')->assertSuccessful();
        $this->artisan('documents:reconcile-pending')->assertSuccessful();

        $jobs = Queue::pushed(ProcessDocumentAnalysis::class);
        self::assertCount(2, $jobs);
        foreach ($jobs as $job) {
            self::assertSame($analysis->id, $job->analysisId);
            self::assertSame($descriptor->sourceVersion, $job->sourceVersion);
            self::assertSame($analysis->active_cycle_id, $job->cycleId);
        }

        $lifecycle = app(DocumentAnalysisLifecycle::class);
        $firstDelivery = $lifecycle->claimPending(
            $jobs[0]->analysisId,
            $descriptor,
            $jobs[0]->cycleId,
        );
        $duplicateDelivery = $lifecycle->claimPending(
            $jobs[1]->analysisId,
            $descriptor,
            $jobs[1]->cycleId,
        );

        self::assertInstanceOf(DocumentProcessingAttempt::class, $firstDelivery);
        self::assertInstanceOf(StaleCycleOutcome::class, $duplicateDelivery);
        self::assertSame('not_claimable', $duplicateDelivery->reason);
        self::assertSame(ProcessedDocument::STATUS_PROCESSING, $analysis->fresh()->processing_status);
        self::assertSame(1, $analysis->fresh()->cycle_attempt_count);
        self::assertSame(1, DocumentProcessingAttempt::query()
            ->where('processed_document_id', $analysis->id)
            ->count());
    }

    public function test_loading_the_osas_application_review_page_never_invokes_reconciliation(): void
    {
        $applicationId = $this->createApplyOrg();
        $analysis = $this->analysis(
            ProcessedDocument::STATUS_PENDING,
            now(),
            'source-page-load',
            'cycle-page-load',
            $applicationId,
        );
        $user = (new User)->forceFill([
            'id' => 1,
            'role' => 'osas_admin',
            'first_name' => 'OSAS',
            'last_name' => 'Reviewer',
        ]);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureActiveAccount::class);
        $this->actingAs($user);
        View::shouldReceive('share')->zeroOrMoreTimes()->andReturnSelf();
        View::shouldReceive('make')
            ->once()
            ->with(
                'osas.apply-org.show',
                \Mockery::on(fn (array $data): bool => $data['request']->getKey() === $applicationId),
                [],
            )
            ->andReturn(response('OSAS application review'));

        $this->get(route('osas.apply-org.show', ['applyOrg' => $applicationId]))
            ->assertOk();

        Queue::assertNothingPushed();
        self::assertSame(ProcessedDocument::STATUS_PENDING, $analysis->fresh()->processing_status);
        self::assertSame('cycle-page-load', $analysis->fresh()->active_cycle_id);
        self::assertSame(0, $analysis->fresh()->cycle_attempt_count);
    }

    public function test_it_rejects_a_batch_larger_than_the_hard_cap(): void
    {
        $this->analysis('pending', now(), 'source-current', 'cycle-current');

        $this->artisan('documents:reconcile-pending', ['--limit' => 501])
            ->expectsOutput('The --limit option must be an integer between 1 and 500.')
            ->assertFailed();

        Queue::assertNothingPushed();
    }

    private function analysis(
        string $status,
        mixed $associationReadyAt,
        ?string $sourceVersion,
        ?string $cycleId,
        ?int $applicationId = null,
        array $overrides = [],
    ): ProcessedDocument {
        $analysis = (new ProcessedDocument)->forceFill(array_merge([
            'apply_org_id' => $applicationId,
            'document_field' => 'letter_of_application',
            'document_slot' => 'primary',
            'original_filename' => 'application.pdf',
            'file_path' => 'documents/application.pdf',
            'storage_disk' => 'private',
            'source_version' => $sourceVersion,
            'source_fingerprint' => str_repeat('a', 64),
            'processing_status' => $status,
            'association_ready_at' => $associationReadyAt,
            'active_cycle_id' => $cycleId,
            'cycle_kind' => 'initial',
            'cycle_attempt_count' => 0,
        ], $overrides));
        $analysis->save();

        return $analysis;
    }

    /** @return array{ProcessedDocument, StoredDocumentDescriptor} */
    private function readyAssociatedAnalysis(): array
    {
        $applicationId = $this->createApplyOrg();
        $sourceVersion = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $cycleId = '11111111-1111-4111-8111-111111111111';
        $fingerprint = str_repeat('b', 64);
        $analysis = $this->analysis(
            ProcessedDocument::STATUS_PENDING,
            now(),
            $sourceVersion,
            $cycleId,
            $applicationId,
            ['source_fingerprint' => $fingerprint],
        );

        return [$analysis, new StoredDocumentDescriptor(
            $applicationId,
            'letter_of_application',
            'primary',
            'Letter of Application',
            'application.pdf',
            'private',
            'documents/application.pdf',
            $sourceVersion,
            $fingerprint,
        )];
    }

    private function createApplyOrg(): int
    {
        return DB::table('apply_orgs')->insertGetId([
            'organization_name' => 'Test Organization',
            'letter_of_application' => 'documents/application.pdf',
            'status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
        });
        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('organization_name')->nullable();
            $table->string('letter_of_application')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamps();
        });
        Schema::create('processed_documents', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('apply_org_id')->nullable();
            $table->string('document_field')->nullable();
            $table->string('document_slot')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('file_path')->nullable();
            $table->string('storage_disk')->nullable();
            $table->string('source_version')->nullable();
            $table->string('source_fingerprint', 64)->nullable();
            $table->timestamp('association_ready_at')->nullable();
            $table->string('active_cycle_id')->nullable();
            $table->string('cycle_kind')->nullable();
            $table->unsignedTinyInteger('cycle_attempt_count')->default(0);
            $table->timestamp('processing_started_at')->nullable();
            $table->string('processing_status');
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
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['processed_document_id', 'cycle_id', 'ordinal']);
        });
    }
}
