<?php

namespace App\Services;

use App\Events\OsasProposalQueueUpdated;
use App\Models\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class OsasProposalQueueService
{
    public function pendingCount(): int
    {
        if (! $this->schemaReady()) {
            return 0;
        }

        return Event::query()
            ->where('osas_status', 'pending')
            ->count();
    }

    public function broadcastUpdated(): void
    {
        // Queue updates must also reach the OSAS account that made a decision,
        // so do not use toOthers() here.
        safe_broadcast(new OsasProposalQueueUpdated($this->pendingCount()));
    }

    /**
     * Cache the schema introspection check (two remote queries otherwise run
     * on every single page render). The events table/schema only changes via
     * migrations, so a 10-minute cache is perfectly safe.
     */
    private function schemaReady(): bool
    {
        return (bool) Cache::remember('osas.proposal_queue.schema_ready', 600, function () {
            return Schema::hasTable('events') && Schema::hasColumn('events', 'osas_status');
        });
    }
}
