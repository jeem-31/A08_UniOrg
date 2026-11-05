<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EdushareItemDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $itemId;

    public function __construct($itemId)
    {
        $this->itemId = $itemId;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('edushare'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'edushare-item-deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->itemId,
        ];
    }
}
