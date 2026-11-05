<?php

namespace App\Jobs;

use App\Models\ApplyOrg;
use App\Models\User;
use App\Services\StudentListProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessApprovedApplication implements ShouldQueue
{
    use Queueable;

    /**
     * Number of times the job may be attempted before failing.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying after a failure.
     */
    public int $backoff = 30;

    public function __construct(
        public int $applyOrgId,
        public int $requesterId,
    ) {
        if (config('queue.default') !== 'sync') {
            $this->onConnection('redis')->onQueue('default');
        }
    }

    public function handle(StudentListProcessor $processor): void
    {
        $applyOrg = ApplyOrg::find($this->applyOrgId);

        if (! $applyOrg) {
            Log::error("[ProcessApprovedApplication] ApplyOrg #{$this->applyOrgId} not found — job aborted.");
            return;
        }

        Log::info("[ProcessApprovedApplication] Starting OCR for ApplyOrg #{$applyOrg->id} ({$applyOrg->organization_name})");

        try {
            $summary = $processor->process($applyOrg);

            Log::info(
                "[ProcessApprovedApplication] OCR complete for ApplyOrg #{$applyOrg->id}",
                $summary,
            );
        } catch (\Throwable $e) {
            Log::error("[ProcessApprovedApplication] OCR failed for ApplyOrg #{$applyOrg->id}: " . $e->getMessage());

            // Re-throw so Laravel's queue worker retries it (up to $tries times)
            throw $e;
        }
    }
}
