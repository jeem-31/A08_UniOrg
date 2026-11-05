<?php

namespace App\Events;

use App\Models\DocumentTemplate;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $document;

    public function __construct(DocumentTemplate $document)
    {
        $this->document = $document;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('documents'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'document-created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->document->id,
            'name' => $this->document->name,
            'type' => $this->document->type,
        ];
    }
}
