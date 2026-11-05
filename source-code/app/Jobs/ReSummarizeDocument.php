<?php

namespace App\Jobs;

use App\Services\DocumentIntelligence\ReSummarizationOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

final class ReSummarizeDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public ?int $maxExceptions = null;

    public bool $failOnTimeout = true;

    /** Back off between transient provider failures (429/503/timeout) so the
     * free-tier rate limit can reset before the next attempt. maxExceptions is
     * left null so exceptions honor $tries + $backoff instead of failing the
     * job on the first exception. */
    public array $backoff = [15, 60];

    public function __construct(
        public int $analysisId,
        public string $sourceVersion,
        public string $summaryCycleId,
    ) {
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

    public function handle(ReSummarizationOrchestrator $orchestrator): void
    {
        $orchestrator->refresh($this->analysisId, $this->sourceVersion, $this->summaryCycleId);
    }

    public function failed(?Throwable $failure): void
    {
        if ($failure !== null) {
            app(ReSummarizationOrchestrator::class)->recordTerminalFailure(
                $this->analysisId,
                $this->sourceVersion,
                $this->summaryCycleId,
                $failure,
            );
        }
    }
}
