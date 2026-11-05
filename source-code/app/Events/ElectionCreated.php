<?php

namespace App\Events;

use App\Models\Election;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ElectionCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $election;

    public function __construct(Election $election)
    {
        $this->election = $election;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('elections'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'election-created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->election->id,
            'title' => $this->election->title,
            'status' => $this->election->status,
        ];
    }
}
