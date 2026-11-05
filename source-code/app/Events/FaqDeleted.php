<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FaqDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $faqId;

    public function __construct($faqId)
    {
        $this->faqId = $faqId;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('faqs'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'faq-deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->faqId,
        ];
    }
}
