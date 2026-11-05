<?php

namespace App\Events;

use App\Models\EdushareItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EdushareItemUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $item;

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
        return 'edushare-item-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->item->id,
            'title' => $this->item->title,
        ];
    }
}
