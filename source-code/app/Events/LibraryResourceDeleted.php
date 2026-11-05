<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LibraryResourceDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $resourceId;

    public function __construct($resourceId)
    {
        $this->resourceId = $resourceId;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('library'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'library-resource-deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->resourceId,
        ];
    }
}
