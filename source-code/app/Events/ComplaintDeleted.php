<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComplaintDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $complaintId;

    public function __construct($complaintId)
    {
        $this->complaintId = $complaintId;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('complaints'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'complaint-deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->complaintId,
        ];
    }
}
