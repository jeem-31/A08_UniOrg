<?php

namespace App\Jobs;

use App\Services\DocumentIntelligence\DocumentTagRefresher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReTagDocument implements ShouldQueue
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

    public function handle(DocumentTagRefresher $refresher): void
    {
        $refresher->refresh($this->analysisId, $this->sourceVersion);
    }

    public function failed(?Throwable $failure): void
    {
        // No status columns exist for re-tagging: the previous tags stay in
        // place and the queue failure is the signal. Never throws.
        try {
            Log::error('Document re-tagging failed; previous tags retained.', [
                'analysis_id' => $this->analysisId,
                'message' => $failure?->getMessage(),
            ]);
        } catch (\Throwable) {
        }
    }
}
