<?php

namespace App\Events;

use App\Models\EdushareItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EdushareItemCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $item;
    
    // Make broadcasting synchronous to avoid queue delays
    public $broadcastQueue = null;

    public function __construct(EdushareItem $item)
    {
        $this->item = $item;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('edushare'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'edushare-item-created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->item->id,
            'name' => $this->item->name,
            'type' => $this->item->type,
        ];
    }
}
