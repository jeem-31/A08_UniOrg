<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** **Validates: Requirements 1.2, 2.1, 2.6, 8.1, 8.4, 8.5, 9.1** */
final class DocumentAnalysisMigrationContractTest extends TestCase
{
    private string $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migration = $this->source(
            'database/migrations/2026_07_16_000000_add_document_analysis_lifecycle_and_attempt_ledger.php'
        );
    }

    public function test_schema_declares_lifecycle_domains_and_completion_invariant(): void
    {
        $legacySchema = $this->source(
            'database/migrations/2026_04_28_221826_create_processed_documents_table.php'
        );

        self::assertStringContainsString(
            "['pending', 'processing', 'completed', 'failed']",
            $legacySchema,
        );
        self::assertStringContainsString(
            "cycle_kind IS NULL OR cycle_kind IN ('initial', 'retry')",
            $this->migration,
        );
        self::assertStringContainsString('cycle_attempt_count BETWEEN 0 AND 3', $this->migration);
        self::assertStringContainsString(
            "resummarization_status IN ('idle', 'processing', 'failed')",
            $this->migration,
        );
        self::assertStringContainsString(
            "processing_status <> 'completed' OR (processed_at IS NOT NULL AND source_version IS NOT NULL AND source_fingerprint IS NOT NULL AND output_source_version = source_version)",
            $this->migration,
        );
    }

    public function test_logical_records_and_attempt_deliveries_have_database_uniqueness_keys(): void
    {
        self::assertStringContainsString(
            "['apply_org_id', 'document_field', 'document_slot']",
            $this->migration,
        );
        self::assertStringContainsString('pd_apply_field_slot_uq', $this->migration);
        self::assertStringContainsString(
            "['processed_document_id', 'cycle_id', 'ordinal']",
            $this->migration,
        );
        self::assertStringContainsString('dpa_document_cycle_ordinal_uq', $this->migration);
        self::assertStringContainsString('ordinal BETWEEN 1 AND 3', $this->migration);
    }

    public function test_backfill_has_deterministic_completed_pending_and_safe_failure_outcomes(): void
    {
        $body = $this->methodBody('publishBackfill');

        self::assertStringContainsString("\$update['output_source_version'] = \$item['source_version']", $body);
        self::assertStringContainsString("\$update['summary_processed_at'] = \$row->processed_at", $body);
        self::assertStringContainsString("\$update['processing_status'] = 'pending'", $body);
        self::assertStringContainsString("\$update['cycle_kind'] = 'initial'", $body);
        self::assertStringContainsString('legacy_association_unverifiable', $body);
        self::assertStringContainsString('legacy_source_unverifiable', $body);
        self::assertStringContainsString('legacy_processing_failed', $body);
        self::assertStringContainsString('legacy_output_invalid', $body);
    }

    public function test_backfill_is_read_only_for_sources_associations_and_review_status(): void
    {
        $publication = $this->methodBody('publishBackfill');
        $reconciliation = $this->methodBody('backfillAndReconcileLegacyRows');
        $fingerprint = $this->methodBody('fingerprint');

        foreach (['file_path', 'apply_org_id', 'document_field'] as $protectedColumn) {
            self::assertStringNotContainsString("'{$protectedColumn}' =>", $publication);
        }

        self::assertStringNotContainsString("DB::table('apply_orgs')->update", $this->migration);
        self::assertStringNotContainsString("DB::table('apply_orgs')->delete", $this->migration);
        self::assertStringNotContainsString("Schema::table('apply_orgs'", $this->migration);
        self::assertStringNotContainsString("'status' =>", $publication);
        self::assertStringContainsString('readStream(', $fingerprint);
        self::assertDoesNotMatchRegularExpression(
            '/Storage::(?:disk\([^)]*\)->)?(?:put|write|delete|move|copy)\s*\(/',
            $this->migration,
        );
        self::assertStringContainsString("DB::table('processed_documents')->whereIn('id', \$ids)->delete()", $reconciliation);
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);

        self::assertNotFalse($source);

        return $source;
    }

    private function methodBody(string $method): string
    {
        $start = strpos($this->migration, "private function {$method}(");
        self::assertNotFalse($start, "Migration method {$method} was not found.");

        $open = strpos($this->migration, '{', $start);
        self::assertNotFalse($open);
        $depth = 1;

        for ($offset = $open + 1, $length = strlen($this->migration); $offset < $length; $offset++) {
            $depth += $this->migration[$offset] === '{' ? 1 : 0;
            $depth -= $this->migration[$offset] === '}' ? 1 : 0;
            if ($depth === 0) {
                return substr($this->migration, $open + 1, $offset - $open - 1);
            }
        }

        self::fail("Migration method {$method} is not balanced.");
    }
}
