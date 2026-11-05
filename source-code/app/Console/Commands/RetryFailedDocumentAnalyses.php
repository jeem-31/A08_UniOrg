<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDocumentAnalysis;
use App\Models\ProcessedDocument;
use App\Services\DocumentIntelligence\DocumentAnalysisLifecycle;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;
use App\Services\DocumentIntelligence\SupportedDocumentCatalog;
use Illuminate\Console\Command;

/**
 * Automatically retries failed document analyses that are still retryable.
 *
 * Only retries documents that:
 * - Have status = 'failed'
 * - Have a valid source_version and source_fingerprint (association is intact)
 * - Have not exceeded the max retry threshold stored on the record
 *
 * Scheduled to run every 5 minutes so failed jobs are picked up quickly
 * without manual intervention.
 */
final class RetryFailedDocumentAnalyses extends Command
{
    private const MAX_BATCH_SIZE = 200;

    protected $signature = 'documents:retry-failed
        {--limit=50 : Maximum failed analyses to retry per run (1-200)}
        {--dry-run : Show what would be retried without actually retrying}';

    protected $description = 'Automatically retry failed document analyses (SSL errors, timeouts, transient failures)';

    public function __construct(
        private readonly DocumentAnalysisLifecycle $lifecycle,
        private readonly SupportedDocumentCatalog $catalog,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => self::MAX_BATCH_SIZE],
        ]);

        if ($limit === false) {
            $this->error('The --limit option must be an integer between 1 and 200.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        // Fetch failed documents that still have valid source association
        $analyses = ProcessedDocument::query()
            ->select([
                'id', 'apply_org_id', 'document_field', 'document_slot',
                'original_filename', 'storage_disk', 'file_path',
                'source_version', 'source_fingerprint', 'failure_code',
                'processing_status',
            ])
            ->where('processing_status', ProcessedDocument::STATUS_FAILED)
            ->whereNotNull('source_version')
            ->whereNotNull('source_fingerprint')
            ->whereNotNull('association_ready_at')
            // Skip permanently unrecoverable failures
            ->whereNotIn('failure_code', [
                'empty_document',
                'unsupported_document_field',
                'legacy_association_unverifiable',
            ])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($analyses->isEmpty()) {
            $this->info('No failed document analyses found to retry.');
            return self::SUCCESS;
        }

        $retried = 0;
        $skipped = 0;

        foreach ($analyses as $analysis) {
            // Skip if catalog doesn't support this document field anymore
            if (! $this->catalog->supports((string) $analysis->document_field)) {
                $skipped++;
                $this->line("  <comment>SKIP</comment> #{$analysis->id} — unsupported field: {$analysis->document_field}");
                continue;
            }

            $label = $this->catalog->definition((string) $analysis->document_field)->label;

            $descriptor = new StoredDocumentDescriptor(
                (int) $analysis->apply_org_id,
                (string) $analysis->document_field,
                (string) $analysis->document_slot,
                $label,
                (string) $analysis->original_filename,
                (string) $analysis->storage_disk,
                (string) $analysis->file_path,
                (string) $analysis->source_version,
                (string) $analysis->source_fingerprint,
            );

            if ($dryRun) {
                $this->line("  <info>WOULD RETRY</info> #{$analysis->id} — {$analysis->document_field} ({$analysis->failure_code})");
                $retried++;
                continue;
            }

            try {
                $result = $this->lifecycle->startRetry((int) $analysis->getKey(), $descriptor);

                if ($result instanceof ProcessedDocument) {
                    ProcessDocumentAnalysis::dispatch(
                        (int) $result->getKey(),
                        (string) $result->source_version,
                        (string) $result->active_cycle_id,
                    );
                    $retried++;
                    $this->line("  <info>RETRIED</info> #{$analysis->id} — {$analysis->document_field}");
                } else {
                    // StaleCycleOutcome — record changed between our read and write
                    $skipped++;
                    $this->line("  <comment>STALE</comment> #{$analysis->id} — record changed, skipping");
                }
            } catch (\Throwable $e) {
                $skipped++;
                $this->line("  <error>ERROR</error> #{$analysis->id} — {$e->getMessage()}");
            }
        }

        $action = $dryRun ? 'Would retry' : 'Retried';
        $this->info("{$action} {$retried} failed document analysis job(s). Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
