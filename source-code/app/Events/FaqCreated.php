<?php

namespace App\Events;

use App\Models\Faq;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FaqCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $faq;

    public function __construct(Faq $faq)
    {
        $this->faq = $faq;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('faqs'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'faq-created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->faq->id,
            'question' => $this->faq->question,
            'answer' => $this->faq->answer,
            'category' => $this->faq->category,
            'keywords' => $this->faq->keywords ?? [],
            'status' => $this->faq->status,
            'is_published' => $this->faq->is_published,
        ];
    }
}
