<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addPortableIndexes();

        match (DB::getDriverName()) {
            'pgsql' => $this->addPostgresTextIndexes(),
            'mysql' => $this->addMySqlTextIndexes(),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => $this->dropPostgresTextIndexes(),
            'mysql' => $this->dropMySqlTextIndexes(),
            default => null,
        };

        Schema::table('processed_documents', function (Blueprint $table): void {
            $table->dropIndex('pd_library_current_output_idx');
        });
        Schema::table('document_library_entries', function (Blueprint $table): void {
            $table->dropIndex('dle_filename_idx');
        });
        Schema::table('apply_orgs', function (Blueprint $table): void {
            $table->dropIndex('dla_org_submitted_idx');
            $table->dropIndex('dla_department_submitted_idx');
            $table->dropIndex('dla_kind_status_submitted_idx');
        });
    }

    private function addPortableIndexes(): void
    {
        Schema::table('apply_orgs', function (Blueprint $table): void {
            $table->index(['organization_id', 'created_at', 'id'], 'dla_org_submitted_idx');
            $table->index(['department_id', 'created_at', 'id'], 'dla_department_submitted_idx');
            $table->index(
                ['application_type', 'status', 'created_at', 'id'],
                'dla_kind_status_submitted_idx'
            );
        });

        Schema::table('document_library_entries', function (Blueprint $table): void {
            // Portable ordering/equality support; production dialects add text-search indexes below.
            $table->index('original_filename', 'dle_filename_idx');
        });

        Schema::table('processed_documents', function (Blueprint $table): void {
            // This is a covering lookup only. Current-output eligibility still requires the
            // completed-state and equal non-null source/output version predicates in the query.
            $table->index([
                'apply_org_id',
                'document_field',
                'document_slot',
                'processing_status',
                'source_version',
                'output_source_version',
                'id',
            ], 'pd_library_current_output_idx');
        });
    }

    private function addPostgresTextIndexes(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement(
            'CREATE INDEX dle_filename_lower_idx ON document_library_entries (LOWER(original_filename))'
        );
        DB::statement(
            'CREATE INDEX dle_filename_trgm_idx ON document_library_entries USING GIN (LOWER(original_filename) gin_trgm_ops)'
        );
        DB::statement(
            "CREATE INDEX pd_summary_trgm_idx ON processed_documents USING GIN (LOWER(COALESCE(summary, '')) gin_trgm_ops)"
        );
        DB::statement(
            "CREATE INDEX pd_extracted_text_trgm_idx ON processed_documents USING GIN (LOWER(COALESCE(extracted_text, '')) gin_trgm_ops)"
        );
        DB::statement(
            "CREATE INDEX pd_tags_trgm_idx ON processed_documents USING GIN (LOWER(COALESCE(tags::text, '')) gin_trgm_ops)"
        );
    }

    private function addMySqlTextIndexes(): void
    {
        DB::statement(
            'ALTER TABLE document_library_entries ADD FULLTEXT INDEX dle_filename_ft_idx (original_filename)'
        );
        DB::statement(
            'ALTER TABLE processed_documents ADD FULLTEXT INDEX pd_generated_text_ft_idx (summary, extracted_text)'
        );
        DB::statement(
            'ALTER TABLE processed_documents ADD COLUMN library_tags_search LONGTEXT GENERATED ALWAYS AS (LOWER(CAST(tags AS CHAR))) STORED INVISIBLE'
        );
        DB::statement(
            'ALTER TABLE processed_documents ADD FULLTEXT INDEX pd_tags_ft_idx (library_tags_search)'
        );
    }

    private function dropPostgresTextIndexes(): void
    {
        foreach ([
            'pd_tags_trgm_idx',
            'pd_extracted_text_trgm_idx',
            'pd_summary_trgm_idx',
            'dle_filename_trgm_idx',
            'dle_filename_lower_idx',
        ] as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }

    private function dropMySqlTextIndexes(): void
    {
        DB::statement('ALTER TABLE processed_documents DROP INDEX pd_tags_ft_idx');
        DB::statement('ALTER TABLE processed_documents DROP COLUMN library_tags_search');
        DB::statement('ALTER TABLE processed_documents DROP INDEX pd_generated_text_ft_idx');
        DB::statement('ALTER TABLE document_library_entries DROP INDEX dle_filename_ft_idx');
    }
};
