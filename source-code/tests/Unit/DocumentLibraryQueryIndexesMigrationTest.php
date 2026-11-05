<?php

namespace Tests\Unit;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** **Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5, 11.1, 11.2** */
final class DocumentLibraryQueryIndexesMigrationTest extends TestCase
{
    private string $originalConnection;

    private bool $sqliteReady = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
    }

    protected function tearDown(): void
    {
        if ($this->sqliteReady) {
            Schema::dropIfExists('document_library_entries');
            Schema::dropIfExists('processed_documents');
            Schema::dropIfExists('apply_orgs');
            DB::purge('sqlite');
            DB::setDefaultConnection($this->originalConnection);
        }

        parent::tearDown();
    }

    public function test_sqlite_adds_and_removes_only_portable_query_indexes(): void
    {
        $this->bootSqlite();
        $migration = $this->migration();
        $migration->up();

        self::assertContains('dla_org_submitted_idx', $this->indexNames('apply_orgs'));
        self::assertContains('dla_department_submitted_idx', $this->indexNames('apply_orgs'));
        self::assertContains('dla_kind_status_submitted_idx', $this->indexNames('apply_orgs'));
        self::assertContains('dle_filename_idx', $this->indexNames('document_library_entries'));
        self::assertContains('pd_library_current_output_idx', $this->indexNames('processed_documents'));
        self::assertNotContains('dle_filename_trgm_idx', $this->indexNames('document_library_entries'));
        self::assertNotContains('dle_filename_ft_idx', $this->indexNames('document_library_entries'));

        $migration->down();

        self::assertNotContains('dla_org_submitted_idx', $this->indexNames('apply_orgs'));
        self::assertNotContains('dle_filename_idx', $this->indexNames('document_library_entries'));
        self::assertNotContains('pd_library_current_output_idx', $this->indexNames('processed_documents'));
        self::assertContains('dle_apply_id_idx', $this->indexNames('document_library_entries'));
    }

    public function test_index_migration_preserves_literal_text_and_current_output_association_data(): void
    {
        $this->bootSqlite();
        DB::table('document_library_entries')->insert([
            'id' => 10,
            'apply_org_id' => 1,
            'document_field' => 'application_form',
            'document_slot' => 'primary',
            'original_filename' => 'Budget 100%_Final\\Copy.pdf',
        ]);
        DB::table('processed_documents')->insert([
            [
                'id' => 20,
                'apply_org_id' => 1,
                'document_field' => 'application_form',
                'document_slot' => 'primary',
                'processing_status' => 'completed',
                'source_version' => 'current-version',
                'output_source_version' => 'current-version',
                'summary' => 'Literal 100%_Final\\Copy',
                'tags' => '["100%_Final\\\\Copy"]',
                'extracted_text' => 'Current searchable output',
            ],
        ]);
        $beforeEntry = DB::table('document_library_entries')->first();
        $beforeAnalysis = DB::table('processed_documents')->first();

        $this->migration()->up();

        self::assertEquals($beforeEntry, DB::table('document_library_entries')->first());
        self::assertEquals($beforeAnalysis, DB::table('processed_documents')->first());
        self::assertSame(
            1,
            DB::table('processed_documents')
                ->where('processing_status', 'completed')
                ->whereNotNull('output_source_version')
                ->whereColumn('source_version', 'output_source_version')
                ->count(),
        );
        self::assertSame(
            'Budget 100%_Final\\Copy.pdf',
            DB::table('document_library_entries')->value('original_filename'),
        );
    }

    public function test_migration_declares_postgres_and_mysql_text_acceleration_without_query_results(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2).'/database/migrations/2026_07_24_000001_add_document_library_query_indexes.php'
        );
        self::assertNotFalse($source);

        self::assertStringContainsString("'pgsql' => \$this->addPostgresTextIndexes()", $source);
        self::assertStringContainsString("'mysql' => \$this->addMySqlTextIndexes()", $source);
        self::assertStringContainsString('default => null', $source);
        self::assertStringContainsString('dla_org_submitted_idx', $source);
        self::assertStringContainsString('dla_department_submitted_idx', $source);
        self::assertStringContainsString('dla_kind_status_submitted_idx', $source);
        self::assertStringContainsString('dle_filename_idx', $source);
        self::assertStringContainsString('pd_library_current_output_idx', $source);
        self::assertStringContainsString("'source_version'", $source);
        self::assertStringContainsString("'output_source_version'", $source);
        self::assertStringContainsString('CREATE EXTENSION IF NOT EXISTS pg_trgm', $source);
        self::assertStringContainsString('LOWER(original_filename) gin_trgm_ops', $source);
        self::assertStringContainsString("LOWER(COALESCE(summary, '')) gin_trgm_ops", $source);
        self::assertStringContainsString("LOWER(COALESCE(extracted_text, '')) gin_trgm_ops", $source);
        self::assertStringContainsString("LOWER(COALESCE(tags::text, '')) gin_trgm_ops", $source);
        self::assertStringContainsString('FULLTEXT INDEX dle_filename_ft_idx', $source);
        self::assertStringContainsString('FULLTEXT INDEX pd_generated_text_ft_idx', $source);
        self::assertStringContainsString('GENERATED ALWAYS AS (LOWER(CAST(tags AS CHAR)))', $source);
        self::assertStringNotContainsString("DB::table('processed_documents')->update", $source);
        self::assertStringNotContainsString("DB::table('document_library_entries')->update", $source);
    }

    private function migration(): Migration
    {
        return require dirname(__DIR__, 2)
            .'/database/migrations/2026_07_24_000001_add_document_library_query_indexes.php';
    }

    /** @return list<string> */
    private function indexNames(string $table): array
    {
        return array_map(
            static fn (object $index): string => $index->name,
            DB::select("PRAGMA index_list('{$table}')"),
        );
    }

    private function bootSqlite(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The pdo_sqlite extension is required for executable SQLite migration validation.');
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->sqliteReady = true;
        $this->createSchema();
    }

    private function createSchema(): void
    {
        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('application_type')->nullable();
            $table->string('request_type')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('processed_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('apply_org_id');
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('processing_status');
            $table->string('source_version')->nullable();
            $table->string('output_source_version')->nullable();
            $table->text('summary')->nullable();
            $table->text('tags')->nullable();
            $table->longText('extracted_text')->nullable();
        });
        Schema::create('document_library_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('apply_org_id');
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('original_filename');
            $table->index(['apply_org_id', 'id'], 'dle_apply_id_idx');
        });
        DB::table('apply_orgs')->insert([
            'id' => 1,
            'organization_id' => 5,
            'department_id' => 8,
            'application_type' => 'apply',
            'request_type' => 'apply',
            'status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
