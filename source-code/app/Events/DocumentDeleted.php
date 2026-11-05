<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $documentId;

    public function __construct($documentId)
    {
        $this->documentId = $documentId;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('documents'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'document-deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->documentId,
        ];
    }
}
