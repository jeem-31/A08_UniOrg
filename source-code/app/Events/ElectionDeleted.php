<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ElectionDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $electionId;

    public function __construct($electionId)
    {
        $this->electionId = $electionId;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('elections'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'election-deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->electionId,
        ];
    }
}
