<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentAnalysis;
use App\Jobs\ReSummarizeDocument;
use App\Models\ApplyOrg;
use App\Models\ProcessedDocument;
use App\Models\User;
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
use App\Services\DocumentIntelligence\DocumentAnalysisRegistrar;
use App\Services\DocumentIntelligence\Exceptions\PermanentDocumentException;
use App\Services\DocumentIntelligence\Exceptions\TransientProcessingException;
use App\Services\DocumentIntelligence\FailureSanitizer;
use App\Services\DocumentIntelligence\Outcomes\PermanentDocumentFailureReason;
use App\Services\DocumentIntelligence\Outcomes\TransientProcessingFailureReason;
use App\Services\DocumentIntelligence\ReSummarizationOrchestrator;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;
use App\Services\DocumentIntelligence\SupportedDocumentCatalog;
use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

/** **Validates: Requirements 1.1, 1.4–1.5, 2.2–2.4, 2.6, 2.8–2.9, 3.9, 4.9, 5.1–5.3, 5.6–5.9, 6.2, 6.4–6.8, 6.10–6.11, 7.1–7.5, 8.1–8.7, 9.1–9.5** */
final class DocumentIntelligenceWorkflowRegressionTest extends TestCase
{
    public const DISK = 'document-workflow-e2e';

    private WorkflowFakeSourceReader $reader;

    private WorkflowFakeExtractor $extractor;

    private WorkflowFakeProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        Storage::fake(self::DISK);
        Queue::fake();
        $this->reader = new WorkflowFakeSourceReader;
        $this->extractor = new WorkflowFakeExtractor;
        $this->provider = new WorkflowFakeProvider;
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('document_processing_attempts');
        Schema::dropIfExists('document_library_entries');
        Schema::dropIfExists('processed_documents');
        Schema::dropIfExists('apply_orgs');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_upload_to_completion_processes_concurrent_documents_and_keeps_retrieval_stable(): void
    {
        $application = $this->application('documents/letter.pdf', 'documents/bylaws.pdf');
        Storage::disk(self::DISK)->put('documents/letter.pdf', 'letter-source-bytes');
        Storage::disk(self::DISK)->put('documents/bylaws.pdf', 'bylaws-source-bytes');
        $registrar = app(DocumentAnalysisRegistrar::class);
        $commands = $this->registerAndQueue($application, [
            $this->descriptor($registrar, $application, 'letter_of_application', 'Letter.pdf', 'documents/letter.pdf', 'letter-source-bytes'),
            $this->descriptor($registrar, $application, 'constitution_bylaws', 'Bylaws.pdf', 'documents/bylaws.pdf', 'bylaws-source-bytes'),
        ]);

        Queue::assertPushed(ProcessDocumentAnalysis::class, 2);
        $sourceBefore = ProcessedDocument::query()->get()->mapWithKeys(
            fn (ProcessedDocument $analysis): array => [$analysis->id => $this->sourceSnapshot($analysis)],
        )->all();
        foreach ($commands as $command) {
            (new ProcessDocumentAnalysis($command->analysisId, $command->sourceVersion, $command->cycleId))
                ->handle($this->orchestrator());
        }

        $analyses = ProcessedDocument::query()->orderBy('document_field')->get();
        self::assertCount(2, $analyses);
        foreach ($analyses as $analysis) {
            self::assertSame(ProcessedDocument::STATUS_COMPLETED, $analysis->processing_status);
            self::assertSame($analysis->source_version, $analysis->output_source_version);
            self::assertSame($sourceBefore[$analysis->id], $this->sourceSnapshot($analysis));
        }
        self::assertSame('letter-source-bytes', Storage::disk(self::DISK)->get('documents/letter.pdf'));
        self::assertSame('bylaws-source-bytes', Storage::disk(self::DISK)->get('documents/bylaws.pdf'));
        self::assertSame('Under Review', $application->fresh()->status);
        self::assertSame('documents/letter.pdf', $application->fresh()->letter_of_application);
        self::assertSame('documents/bylaws.pdf', $application->fresh()->constitution_bylaws);

        $analysis = $analyses->firstWhere('document_field', 'letter_of_application');
        $this->actingAs($this->user('osas_admin'));
        $first = $this->getJson(route('osas.apply-org.analyses.summary', [$application, $analysis]));
        $second = $this->getJson(route('osas.apply-org.analyses.summary', [$application, $analysis]));
        $first->assertOk()->assertJsonPath('summary', 'Safe summary for documents/letter.pdf.');
        self::assertSame($first->json(), $second->json());
        Queue::assertPushed(ProcessDocumentAnalysis::class, 2);
    }

    public function test_transient_exhaustion_and_permanent_failure_manual_retry_preserve_review_state_and_redact_secrets(): void
    {
        $registrar = app(DocumentAnalysisRegistrar::class);
        $transientApplication = $this->application('documents/transient.pdf', null, 'Approved');
        Storage::disk(self::DISK)->put('documents/transient.pdf', 'transient-source-bytes');
        [$transientCommand] = $this->registerAndQueue($transientApplication, [
            $this->descriptor($registrar, $transientApplication, 'letter_of_application', 'Transient.pdf', 'documents/transient.pdf', 'transient-source-bytes'),
        ]);
        $transient = ProcessedDocument::query()->sole();
        $transientSource = $this->sourceSnapshot($transient);
        $this->provider->analysisOutcomes = array_fill(0, 3, new TransientProcessingException(
            TransientProcessingFailureReason::ProviderUnavailable,
            'RAW_PROVIDER_BODY Bearer secret-token',
        ));

        foreach ([true, true, false] as $shouldRelease) {
            $job = (new ProcessDocumentAnalysis(
                $transientCommand->analysisId,
                $transientCommand->sourceVersion,
                $transientCommand->cycleId,
            ))->withFakeQueueInteractions();
            $job->handle($this->orchestrator());
            $shouldRelease ? $job->assertReleased() : $job->assertNotReleased();
        }

        $failedTransient = $transient->fresh();
        self::assertSame(ProcessedDocument::STATUS_FAILED, $failedTransient->processing_status);
        self::assertSame(3, $failedTransient->cycle_attempt_count);
        self::assertSame($transientSource, $this->sourceSnapshot($failedTransient));
        self::assertSame('transient-source-bytes', Storage::disk(self::DISK)->get('documents/transient.pdf'));
        self::assertSame('Approved', $transientApplication->fresh()->status);
        $this->assertSecretsAbsent('RAW_PROVIDER_BODY', 'secret-token');

        $permanentApplication = $this->application('documents/permanent.pdf', null, 'Rejected');
        Storage::disk(self::DISK)->put('documents/permanent.pdf', 'permanent-source-bytes');
        [$permanentCommand] = $this->registerAndQueue($permanentApplication, [
            $this->descriptor($registrar, $permanentApplication, 'letter_of_application', 'Permanent.pdf', 'documents/permanent.pdf', 'permanent-source-bytes'),
        ]);
        $permanent = ProcessedDocument::query()->where('apply_org_id', $permanentApplication->id)->sole();
        $permanentSource = $this->sourceSnapshot($permanent);
        $this->extractor->outcomes[] = new PermanentDocumentException(
            PermanentDocumentFailureReason::Unreadable,
            'RAW_PROVIDER_BODY secret-token',
        );
        (new ProcessDocumentAnalysis($permanentCommand->analysisId, $permanentCommand->sourceVersion, $permanentCommand->cycleId))
            ->withFakeQueueInteractions()
            ->handle($this->orchestrator());
        self::assertSame(ProcessedDocument::STATUS_FAILED, $permanent->fresh()->processing_status);
        self::assertSame($permanentSource, $this->sourceSnapshot($permanent->fresh()));

        $denied = $this->actingAs($this->user('student'))->getJson(
            route('osas.apply-org.analyses.status', [$permanentApplication, $permanent]),
        );
        $denied->assertForbidden();
        self::assertStringNotContainsString('RAW_PROVIDER_BODY', $denied->getContent());
        self::assertStringNotContainsString('secret-token', $denied->getContent());

        $this->actingAs($this->user('osas_admin'))->postJson(
            route('osas.apply-org.analyses.retry', [$permanentApplication, $permanent]),
        )->assertAccepted()->assertJsonPath('status', ProcessedDocument::STATUS_PENDING);
        $retry = $permanent->fresh();
        (new ProcessDocumentAnalysis($retry->id, $retry->source_version, $retry->active_cycle_id))
            ->handle($this->orchestrator());

        $completed = $permanent->fresh();
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $completed->processing_status);
        self::assertSame($permanentSource, $this->sourceSnapshot($completed));
        self::assertSame('permanent-source-bytes', Storage::disk(self::DISK)->get('documents/permanent.pdf'));
        self::assertSame('Rejected', $permanentApplication->fresh()->status);
        $this->getJson(route('osas.apply-org.analyses.summary', [$permanentApplication, $completed]))
            ->assertOk()
            ->assertJsonMissing(['RAW_PROVIDER_BODY'])
            ->assertJsonMissing(['secret-token']);
        $this->assertSecretsAbsent('RAW_PROVIDER_BODY', 'secret-token');
    }

    public function test_source_replacement_stale_job_and_resummarization_never_mutate_sources_or_associations(): void
    {
        $application = $this->application('documents/original.pdf');
        Storage::disk(self::DISK)->put('documents/original.pdf', 'original-source-bytes');
        Storage::disk(self::DISK)->put('documents/replacement.pdf', 'replacement-source-bytes');
        $registrar = app(DocumentAnalysisRegistrar::class);
        [$oldCommand] = $this->registerAndQueue($application, [
            $this->descriptor($registrar, $application, 'letter_of_application', 'Original.pdf', 'documents/original.pdf', 'original-source-bytes'),
        ]);
        $analysis = ProcessedDocument::query()->sole();
        $replacementCommand = null;
        $this->provider->beforeAnalyze = function () use ($application, $registrar, &$replacementCommand): void {
            $application->forceFill(['letter_of_application' => 'documents/replacement.pdf'])->save();
            $descriptor = $this->descriptor(
                $registrar,
                $application,
                'letter_of_application',
                'Replacement.pdf',
                'documents/replacement.pdf',
                'replacement-source-bytes',
            );
            $replacementCommand = DB::transaction(
                fn (): array => $registrar->register($application, [$descriptor]),
            )[0];
        };

        (new ProcessDocumentAnalysis($oldCommand->analysisId, $oldCommand->sourceVersion, $oldCommand->cycleId))
            ->handle($this->orchestrator());
        self::assertNotNull($replacementCommand);
        $pendingReplacement = $analysis->fresh();
        $replacementSource = $this->sourceSnapshot($pendingReplacement);
        self::assertSame(ProcessedDocument::STATUS_PENDING, $pendingReplacement->processing_status);
        self::assertSame('documents/replacement.pdf', $pendingReplacement->file_path);
        self::assertNull($pendingReplacement->output_source_version);
        self::assertSame('stale', DB::table('document_processing_attempts')->where('processed_document_id', $analysis->id)->value('outcome'));
        self::assertSame('original-source-bytes', Storage::disk(self::DISK)->get('documents/original.pdf'));
        self::assertSame('replacement-source-bytes', Storage::disk(self::DISK)->get('documents/replacement.pdf'));
        self::assertSame('documents/replacement.pdf', $application->fresh()->letter_of_application);

        (new ProcessDocumentAnalysis(
            $replacementCommand->analysisId,
            $replacementCommand->sourceVersion,
            $replacementCommand->cycleId,
        ))->handle($this->orchestrator());
        $completed = $analysis->fresh();
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $completed->processing_status);
        self::assertSame($replacementSource, $this->sourceSnapshot($completed));
        $outputMetadata = $completed->only([
            'document_type', 'document_type_name', 'tags', 'key_information', 'extracted_text',
            'output_source_version', 'source_version', 'file_path', 'storage_disk', 'original_filename',
        ]);

        $this->actingAs($this->user('osas_admin'))->postJson(
            route('osas.apply-org.analyses.resummarize', [$application, $completed]),
        )->assertAccepted()->assertJsonPath('resummarization.status', 'processing');
        $refreshing = $completed->fresh();
        $sourceBeforeRefresh = $this->sourceSnapshot($refreshing);
        $oldSummary = $refreshing->summary;
        $this->provider->summaryOutcomes[] = new CandidateSummary('A safer, clearer replacement summary.');
        (new ReSummarizeDocument(
            $refreshing->id,
            $refreshing->source_version,
            $refreshing->resummarization_cycle_id,
        ))->handle($this->resummarizationOrchestrator());

        $refreshed = $completed->fresh();
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $refreshed->processing_status);
        self::assertSame('idle', $refreshed->resummarization_status);
        self::assertNotSame($oldSummary, $refreshed->summary);
        self::assertTrue($refreshed->last_resummarization_changed);
        self::assertSame($sourceBeforeRefresh, $this->sourceSnapshot($refreshed));
        self::assertSame($outputMetadata, $refreshed->only(array_keys($outputMetadata)));
        self::assertSame('original-source-bytes', Storage::disk(self::DISK)->get('documents/original.pdf'));
        self::assertSame('replacement-source-bytes', Storage::disk(self::DISK)->get('documents/replacement.pdf'));
        self::assertSame('documents/replacement.pdf', $application->fresh()->letter_of_application);
        self::assertSame('Under Review', $application->fresh()->status);
        $this->assertSecretsAbsent('RAW_PROVIDER_BODY', 'secret-token');
    }

    private function orchestrator(): DocumentAnalysisOrchestrator
    {
        $catalog = new SupportedDocumentCatalog;

        return new DocumentAnalysisOrchestrator(
            new DocumentAnalysisLifecycle($catalog),
            $catalog,
            $this->reader,
            $this->extractor,
            $this->provider,
            new AIOutputValidator,
            new FailureSanitizer,
        );
    }

    private function resummarizationOrchestrator(): ReSummarizationOrchestrator
    {
        $catalog = new SupportedDocumentCatalog;

        return new ReSummarizationOrchestrator(
            new DocumentAnalysisLifecycle($catalog),
            $catalog,
            $this->reader,
            $this->extractor,
            $this->provider,
            new FailureSanitizer,
        );
    }

    /** @param list<StoredDocumentDescriptor> $descriptors */
    private function registerAndQueue(ApplyOrg $application, array $descriptors): array
    {
        $commands = DB::transaction(fn (): array => app(DocumentAnalysisRegistrar::class)->register($application, $descriptors));
        foreach ($commands as $command) {
            ProcessDocumentAnalysis::dispatch($command->analysisId, $command->sourceVersion, $command->cycleId);
        }

        return $commands;
    }

    private function descriptor(
        DocumentAnalysisRegistrar $registrar,
        ApplyOrg $application,
        string $field,
        string $name,
        string $path,
        string $bytes,
    ): StoredDocumentDescriptor {
        return $registrar->descriptorFromBytes(
            $application->id,
            $field,
            'primary',
            $name,
            self::DISK,
            $path,
            $bytes,
        );
    }

    private function application(string $letterPath, ?string $bylawsPath = null, string $status = 'Under Review'): ApplyOrg
    {
        $application = (new ApplyOrg)->forceFill([
            'letter_of_application' => $letterPath,
            'constitution_bylaws' => $bylawsPath,
            'status' => $status,
        ]);
        $application->save();

        return $application;
    }

    private function user(string $role): User
    {
        static $ordinal = 0;
        $ordinal++;
        $user = (new User)->forceFill([
            'name' => "Workflow {$role} {$ordinal}",
            'email' => "workflow-{$role}-{$ordinal}@example.test",
            'password' => 'not-used',
            'role' => $role,
            'is_active' => true,
        ]);
        $user->save();

        return $user;
    }

    /** @return array<string, mixed> */
    private function sourceSnapshot(ProcessedDocument $analysis): array
    {
        return $analysis->only([
            'apply_org_id', 'document_field', 'document_slot', 'original_filename', 'file_path',
            'storage_disk', 'source_version', 'source_fingerprint',
        ]);
    }

    private function assertSecretsAbsent(string ...$secrets): void
    {
        $persisted = (string) DB::table('processed_documents')->pluck('error_message')->implode(' ').' '
            .(string) DB::table('document_processing_attempts')->pluck('sanitized_message')->implode(' ');
        foreach ($secrets as $secret) {
            self::assertStringNotContainsString($secret, $persisted);
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
            $table->string('constitution_bylaws')->nullable();
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
        Schema::create('document_library_entries', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('apply_org_id');
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('requirement_label');
            $table->string('original_filename');
            $table->string('storage_disk');
            $table->string('file_path');
            $table->string('source_version')->nullable();
            $table->string('source_fingerprint', 64)->nullable();
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

final class WorkflowFakeSourceReader implements SourceDocumentReader
{
    /** @var list<StoredDocumentDescriptor> */
    public array $opened = [];

    public function open(StoredDocumentDescriptor $source): ReadOnlyDocumentSource
    {
        $this->opened[] = $source;
        $bytes = Storage::disk(DocumentIntelligenceWorkflowRegressionTest::DISK)->get($source->storagePath);

        return new WorkflowReadOnlySource($source, (string) $bytes);
    }
}

final class WorkflowReadOnlySource implements ReadOnlyDocumentSource
{
    public function __construct(
        private readonly StoredDocumentDescriptor $source,
        private readonly string $bytes,
    ) {}

    public function descriptor(): StoredDocumentDescriptor
    {
        return $this->source;
    }

    public function readStream()
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->bytes);
        rewind($stream);

        return $stream;
    }
}

final class WorkflowFakeExtractor implements SourceTextExtractor
{
    /** @var list<ExtractedDocumentText|Throwable> */
    public array $outcomes = [];

    public function extract(ReadOnlyDocumentSource $source): ExtractedDocumentText
    {
        $outcome = array_shift($this->outcomes);
        if ($outcome instanceof Throwable) {
            throw $outcome;
        }

        return $outcome instanceof ExtractedDocumentText
            ? $outcome
            : new ExtractedDocumentText(
                'Fake extracted text for '.$source->descriptor()->storagePath,
                'application/pdf',
                'fake-extractor',
            );
    }
}

final class WorkflowFakeProvider implements DocumentIntelligenceProvider
{
    /** @var list<CandidateAIOutput|Throwable> */
    public array $analysisOutcomes = [];

    /** @var list<CandidateSummary|Throwable> */
    public array $summaryOutcomes = [];

    public ?Closure $beforeAnalyze = null;

    public function analyze(ExtractedDocumentText $text): CandidateAIOutput
    {
        if ($this->beforeAnalyze !== null) {
            $callback = $this->beforeAnalyze;
            $this->beforeAnalyze = null;
            $callback();
        }
        $outcome = array_shift($this->analysisOutcomes);
        if ($outcome instanceof Throwable) {
            throw $outcome;
        }

        return $outcome instanceof CandidateAIOutput ? $outcome : new CandidateAIOutput(
            $text,
            'supported_document',
            'Supported Document',
            'Safe summary for '.str_replace('Fake extracted text for ', '', $text->text).'.',
            ['source' => 'fake'],
            ['supported document'],
        );
    }

    public function summarize(ExtractedDocumentText $text): CandidateSummary
    {
        $outcome = array_shift($this->summaryOutcomes);
        if ($outcome instanceof Throwable) {
            throw $outcome;
        }

        return $outcome instanceof CandidateSummary ? $outcome : new CandidateSummary('Safe refreshed summary.');
    }
}
