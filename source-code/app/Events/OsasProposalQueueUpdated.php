<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OsasProposalQueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        private readonly int $pendingProposalsCount,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('osas-proposals')];
    }

    public function broadcastAs(): string
    {
        return 'osas-proposal-queue-updated';
    }

    /** @return array{pending_proposals_count: int} */
    public function broadcastWith(): array
    {
        return ['pending_proposals_count' => $this->pendingProposalsCount];
    }
}
