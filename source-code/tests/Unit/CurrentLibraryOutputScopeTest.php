<?php

namespace Tests\Unit;

use App\Models\DocumentLibraryEntry;
use App\Models\ProcessedDocument;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** **Validates: Requirements 4.7, 4.8, 4.9, 7.1, 7.2, 7.3, 7.4, 7.6** */
final class CurrentLibraryOutputScopeTest extends TestCase
{
    private string $originalConnection;

    private bool $sqliteReady = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The pdo_sqlite extension is required for current-output scope tests.');
        }

        $this->originalConnection = DB::getDefaultConnection();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->sqliteReady = true;
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        if ($this->sqliteReady) {
            Schema::dropIfExists('processed_documents');
            Schema::dropIfExists('document_library_entries');
            DB::purge('sqlite');
            DB::setDefaultConnection($this->originalConnection);
        }

        parent::tearDown();
    }

    public function test_completed_output_for_the_same_current_source_is_projected_once(): void
    {
        $this->insertEntry(1, 10, [
            'file_path' => '\\documents\\current.pdf',
            'source_fingerprint' => strtoupper(str_repeat('a', 64)),
        ]);
        $this->insertAnalysis(101, 10, [
            'file_path' => '/documents/current.pdf',
            'source_fingerprint' => str_repeat('a', 64),
            'processing_status' => ProcessedDocument::STATUS_COMPLETED,
            'output_source_version' => 'source-v1',
            'document_type' => 'constitution',
            'document_type_name' => 'Constitution',
            'summary' => 'Current summary',
            'tags' => json_encode(['policy'], JSON_THROW_ON_ERROR),
            'key_information' => json_encode(['school_year' => '2025-2026'], JSON_THROW_ON_ERROR),
            'extracted_text' => 'Current extracted text',
            'confidence_score' => 91.25,
            'processed_at' => '2026-01-02 03:04:05',
            'failure_code' => 'must-not-leak',
            'error_message' => 'must-not-leak',
        ]);

        $rows = DocumentLibraryEntry::query()->withCurrentLibraryOutput()->get();
        $row = $rows->sole();

        self::assertSame(1, $rows->count());
        self::assertSame(101, $row->analysis_id);
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $row->processing_status);
        self::assertSame(1, $row->has_current_output);
        self::assertSame('constitution', $row->document_type);
        self::assertSame('Constitution', $row->document_type_name);
        self::assertSame('Current summary', $row->summary);
        self::assertSame('Current extracted text', $row->extracted_text);
        self::assertSame('2026-01-02 03:04:05', $row->processed_at);
        self::assertArrayNotHasKey('failure_code', $row->getAttributes());
        self::assertArrayNotHasKey('error_message', $row->getAttributes());
    }

    public function test_current_non_completed_statuses_are_retained_without_generated_output(): void
    {
        foreach ([
            ProcessedDocument::STATUS_PENDING,
            ProcessedDocument::STATUS_PROCESSING,
            ProcessedDocument::STATUS_FAILED,
        ] as $offset => $status) {
            $applicationId = 20 + $offset;
            $this->insertEntry(20 + $offset, $applicationId);
            $this->insertAnalysis(120 + $offset, $applicationId, [
                'processing_status' => $status,
                'output_source_version' => 'source-v1',
                'summary' => "{$status} output must be hidden",
                'error_message' => "{$status} raw error must be hidden",
            ]);
        }

        $rows = DocumentLibraryEntry::query()
            ->withCurrentLibraryOutput()
            ->orderBy('document_library_entries.id')
            ->get();

        self::assertSame([
            ProcessedDocument::STATUS_PENDING,
            ProcessedDocument::STATUS_PROCESSING,
            ProcessedDocument::STATUS_FAILED,
        ], $rows->pluck('processing_status')->all());

        foreach ($rows as $row) {
            self::assertSame(0, $row->has_current_output);
            self::assertNull($row->summary);
            self::assertNull($row->document_type);
            self::assertNull($row->tags);
            self::assertNull($row->key_information);
            self::assertNull($row->extracted_text);
            self::assertNull($row->processed_at);
            self::assertArrayNotHasKey('error_message', $row->getAttributes());
        }
    }

    public function test_stale_output_and_non_current_descriptors_cannot_expose_generated_fields(): void
    {
        $cases = [
            30 => [[], ['output_source_version' => 'stale-v0'], ProcessedDocument::STATUS_COMPLETED],
            31 => [['source_version' => null], [], ProcessedDocument::STATUS_COMPLETED],
            32 => [[], ['storage_disk' => 'other'], null],
            33 => [[], ['file_path' => 'documents/other.pdf'], null],
            34 => [[], ['source_fingerprint' => str_repeat('b', 64)], null],
            35 => [[], ['source_version' => 'other-v2', 'output_source_version' => 'other-v2'], null],
            36 => [[], ['association_ready_at' => null], null],
            37 => [[], ['document_field' => 'constitution_bylaws'], null],
            38 => [[], ['document_slot' => 'member-1'], null],
        ];

        foreach ($cases as $id => [$entryOverrides, $analysisOverrides]) {
            $this->insertEntry($id, $id, $entryOverrides);
            $this->insertAnalysis(100 + $id, $id, array_merge([
                'processing_status' => ProcessedDocument::STATUS_COMPLETED,
                'output_source_version' => 'source-v1',
                'summary' => 'Non-current output',
            ], $analysisOverrides));
        }

        $rows = DocumentLibraryEntry::query()
            ->withCurrentLibraryOutput()
            ->orderBy('document_library_entries.id')
            ->get()
            ->keyBy('id');

        self::assertCount(count($cases), $rows);
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $rows[30]->processing_status);
        self::assertSame(ProcessedDocument::STATUS_COMPLETED, $rows[31]->processing_status);

        foreach ([32, 33, 34, 35, 36, 37, 38] as $id) {
            self::assertNull($rows[$id]->processing_status);
            self::assertNull($rows[$id]->analysis_id);
        }

        foreach ($rows as $row) {
            self::assertSame(0, $row->has_current_output);
            self::assertNull($row->summary);
            self::assertNull($row->document_type);
            self::assertNull($row->processed_at);
        }
    }

    /** @param array<string, mixed> $overrides */
    private function insertEntry(int $id, int $applicationId, array $overrides = []): void
    {
        DB::table('document_library_entries')->insert(array_merge([
            'id' => $id,
            'apply_org_id' => $applicationId,
            'document_field' => 'application_form',
            'document_slot' => 'primary',
            'requirement_label' => 'Application Form',
            'original_filename' => "application-{$id}.pdf",
            'storage_disk' => 'private',
            'file_path' => 'documents/current.pdf',
            'source_version' => 'source-v1',
            'source_fingerprint' => str_repeat('a', 64),
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function insertAnalysis(int $id, int $applicationId, array $overrides = []): void
    {
        DB::table('processed_documents')->insert(array_merge([
            'id' => $id,
            'apply_org_id' => $applicationId,
            'document_field' => 'application_form',
            'document_slot' => 'primary',
            'storage_disk' => 'private',
            'file_path' => 'documents/current.pdf',
            'source_version' => 'source-v1',
            'source_fingerprint' => str_repeat('a', 64),
            'association_ready_at' => '2026-01-01 00:00:00',
            'output_source_version' => null,
            'processing_status' => ProcessedDocument::STATUS_PENDING,
            'processed_at' => null,
            'document_type' => null,
            'document_type_name' => null,
            'summary' => null,
            'tags' => null,
            'key_information' => null,
            'extracted_text' => null,
            'confidence_score' => null,
            'failure_code' => null,
            'error_message' => null,
        ], $overrides));
    }

    private function createSchema(): void
    {
        Schema::create('document_library_entries', function (Blueprint $table): void {
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
        });

        Schema::create('processed_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('apply_org_id');
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('storage_disk');
            $table->string('file_path');
            $table->string('source_version')->nullable();
            $table->string('source_fingerprint', 64)->nullable();
            $table->timestamp('association_ready_at')->nullable();
            $table->string('output_source_version')->nullable();
            $table->string('processing_status');
            $table->timestamp('processed_at')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_type_name')->nullable();
            $table->text('summary')->nullable();
            $table->text('tags')->nullable();
            $table->text('key_information')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('failure_code')->nullable();
            $table->text('error_message')->nullable();
            $table->unique(['apply_org_id', 'document_field', 'document_slot']);
        });
    }
}
