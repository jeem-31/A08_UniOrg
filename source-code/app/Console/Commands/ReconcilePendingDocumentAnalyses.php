<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDocumentAnalysis;
use App\Models\ProcessedDocument;
use Illuminate\Console\Command;

final class ReconcilePendingDocumentAnalyses extends Command
{
    private const MAX_BATCH_SIZE = 500;

    protected $signature = 'documents:reconcile-pending
        {--limit=100 : Maximum pending analyses to dispatch (1-500)}';

    protected $description = 'Redeliver ready pending document analyses after a queue dispatch outage';

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => self::MAX_BATCH_SIZE],
        ]);
        if ($limit === false) {
            $this->error('The --limit option must be an integer between 1 and 500.');

            return self::FAILURE;
        }

        $analyses = ProcessedDocument::query()
            ->select(['id', 'source_version', 'active_cycle_id'])
            ->where('processing_status', ProcessedDocument::STATUS_PENDING)
            ->whereNotNull('association_ready_at')
            ->whereNotNull('source_version')
            ->whereNotNull('active_cycle_id')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($analyses as $analysis) {
            ProcessDocumentAnalysis::dispatch(
                (int) $analysis->getKey(),
                (string) $analysis->source_version,
                (string) $analysis->active_cycle_id,
            );
        }

        $this->info("Dispatched {$analyses->count()} pending document analysis job(s).");

        return self::SUCCESS;
    }
}
