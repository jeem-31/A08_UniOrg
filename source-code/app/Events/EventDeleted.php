<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $eventId;

    public function __construct($eventId)
    {
        $this->eventId = $eventId;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('events'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'event-deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->eventId,
        ];
    }
}
