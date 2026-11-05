<?php

namespace App\Jobs;

use App\Models\ProcessedDocument;
use App\Services\DocumentIntelligence\DocumentAnalysisOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Asynchronous handoff for a durably registered document-analysis cycle.
 */
final class ProcessDocumentAnalysis implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $maxExceptions = 1;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $analysisId,
        public string $sourceVersion,
        public string $cycleId,
    ) {
        // Use redis connection (matches QUEUE_CONNECTION=redis in .env).
        // Skip when running in sync mode (e.g. tests).
        if (config('queue.default') !== 'sync') {
            $this->onConnection('redis');
        }
        $this->onQueue('document-ai');
        $this->afterCommit();
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->analysisId))
                ->withPrefix('document-analysis:')
                ->dontRelease(),
        ];
    }

    public function handle(DocumentAnalysisOrchestrator $orchestrator): void
    {
        $result = $orchestrator->process(
            $this->analysisId,
            $this->sourceVersion,
            $this->cycleId,
        );

        if ($result instanceof ProcessedDocument
            && $result->processing_status === ProcessedDocument::STATUS_PENDING
            && hash_equals((string) $result->source_version, $this->sourceVersion)
            && hash_equals((string) $result->active_cycle_id, $this->cycleId)) {
            // Transient provider failure: retry after a short delay so rate
            // limits (429) and outages can clear before the next attempt.
            $this->release($this->attempts() < 3 ? 15 : 60);
        }
    }

    public function failed(?Throwable $failure): void
    {
        if ($failure === null) {
            return;
        }

        app(DocumentAnalysisOrchestrator::class)->recordTerminalFailure(
            $this->analysisId,
            $this->sourceVersion,
            $this->cycleId,
            $failure,
        );
    }
}
