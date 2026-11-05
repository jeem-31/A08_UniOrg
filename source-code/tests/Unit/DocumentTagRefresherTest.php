<?php

namespace Tests\Unit;

use App\Jobs\ReTagDocument;
use App\Models\ProcessedDocument;
use App\Services\DocumentIntelligence\AIOutputValidator;
use App\Services\DocumentIntelligence\Contracts\DocumentIntelligenceProvider;
use App\Services\DocumentIntelligence\Contracts\SourceDocumentReader;
use App\Services\DocumentIntelligence\Contracts\SourceTextExtractor;
use App\Services\DocumentIntelligence\Data\CandidateAIOutput;
use App\Services\DocumentIntelligence\Data\CandidateSummary;
use App\Services\DocumentIntelligence\Data\ExtractedDocumentText;
use App\Services\DocumentIntelligence\Data\ReadOnlyDocumentSource;
use App\Services\DocumentIntelligence\DocumentTagRefresher;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;
use App\Services\DocumentIntelligence\SupportedDocumentCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Throwable;

final class DocumentTagRefresherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('processed_documents');

        parent::tearDown();
    }

    public function test_refresh_updates_only_tags_and_preserves_summary(): void
    {
        $analysis = $this->completedAnalysis();
        $provider = new RetagFakeProvider(
            new CandidateAIOutput(
                new ExtractedDocumentText('Retained text.'),
                'accomplishment_reports',
                'Accomplishment Report',
                'A brand new summary that must be ignored.',
                [],
                ['fresh-tag', 'Accomplishment Report'],
            )
        );
        $refresher = $this->refresher($provider);

        $result = $refresher->refresh((int) $analysis->getKey(), (string) $analysis->source_version);

        self::assertNotNull($result);
        self::assertSame(1, $provider->analyzeCalls);
        self::assertSame(['fresh-tag', 'Accomplishment Report'], $result->tags);
        self::assertSame('Old summary.', $result->summary);
        self::assertSame('Old summary.', $analysis->fresh()->summary);
    }

    public function test_refresh_returns_null_for_stale_or_incomplete_output(): void
    {
        $stale = $this->completedAnalysis(['output_source_version' => 'stale-version']);
        $pending = $this->completedAnalysis([
            'processing_status' => ProcessedDocument::STATUS_PENDING,
            'source_version' => 'pending-source',
            'output_source_version' => 'pending-source',
        ]);
        $provider = new RetagFakeProvider(
            new CandidateAIOutput(
                new ExtractedDocumentText('Retained text.'),
                'accomplishment_reports',
                'Accomplishment Report',
                'Summary.',
                [],
                ['fresh-tag'],
            )
        );
        $refresher = $this->refresher($provider);

        self::assertNull($refresher->refresh((int) $stale->getKey(), (string) $stale->source_version));
        self::assertNull($refresher->refresh((int) $pending->getKey(), (string) $pending->source_version));
        self::assertSame(0, $provider->analyzeCalls);
    }

    public function test_refresh_keeps_old_tags_when_ai_output_is_invalid(): void
    {
        $analysis = $this->completedAnalysis();
        $provider = new RetagFakeProvider(
            new CandidateAIOutput(
                new ExtractedDocumentText('Retained text.'),
                'accomplishment_reports',
                'Accomplishment Report',
                'Summary.',
                [],
                [],
            )
        );
        $refresher = $this->refresher($provider);

        $result = $refresher->refresh((int) $analysis->getKey(), (string) $analysis->source_version);

        self::assertNotNull($result);
        self::assertSame(['old-tag'], $result->tags);
        self::assertSame('Old summary.', $result->summary);
    }

    public function test_retag_job_uses_shared_analysis_lock_and_document_ai_queue(): void
    {
        config()->set('queue.default', 'redis');

        $job = new ReTagDocument(42, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $middleware = $job->middleware();

        self::assertSame(42, $job->analysisId);
        self::assertSame('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', $job->sourceVersion);
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

    private function refresher(DocumentIntelligenceProvider $provider): DocumentTagRefresher
    {
        return new DocumentTagRefresher(
            new SupportedDocumentCatalog,
            new RetagThrowingReader,
            new RetagThrowingExtractor,
            $provider,
            new AIOutputValidator,
        );
    }

    /** @param array<string, mixed> $overrides */
    private function completedAnalysis(array $overrides = []): ProcessedDocument
    {
        $analysis = new ProcessedDocument;
        $analysis->forceFill(array_merge([
            'apply_org_id' => 1,
            'document_field' => 'accomplishment_reports',
            'document_slot' => SupportedDocumentCatalog::PRIMARY_SLOT,
            'original_filename' => 'report.pdf',
            'file_path' => 'reports/report.pdf',
            'storage_disk' => 'public',
            'source_version' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'source_fingerprint' => str_repeat('a', 64),
            'association_ready_at' => now(),
            'processing_status' => ProcessedDocument::STATUS_COMPLETED,
            'output_source_version' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'processed_at' => now(),
            'document_type' => 'accomplishment_reports',
            'document_type_name' => 'Accomplishment Report',
            'summary' => 'Old summary.',
            'tags' => ['old-tag'],
            'key_information' => ['organization' => 'Test Org'],
            'extracted_text' => 'Retained text.',
        ], $overrides))->save();

        return $analysis->fresh();
    }

    private function createSchema(): void
    {
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
    }
}

final class RetagThrowingReader implements SourceDocumentReader
{
    public function open(StoredDocumentDescriptor $source): ReadOnlyDocumentSource
    {
        throw new \RuntimeException('Reader must not run when retained text exists.');
    }
}

final class RetagThrowingExtractor implements SourceTextExtractor
{
    public function extract(ReadOnlyDocumentSource $source): ExtractedDocumentText
    {
        throw new \RuntimeException('Extractor must not run when retained text exists.');
    }
}

final class RetagFakeProvider implements DocumentIntelligenceProvider
{
    public int $analyzeCalls = 0;

    public function __construct(private readonly CandidateAIOutput $result) {}

    public function analyze(ExtractedDocumentText $text): CandidateAIOutput
    {
        $this->analyzeCalls++;

        return $this->result;
    }

    public function summarize(ExtractedDocumentText $text): CandidateSummary
    {
        throw new \RuntimeException('Summarize must not run during re-tagging.');
    }
}
