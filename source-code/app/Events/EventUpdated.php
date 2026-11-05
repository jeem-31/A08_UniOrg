<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $event;

    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('events'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'event-updated';
    }

    public function broadcastWith(): array
    {
        // Load user relationship if not loaded
        if (!$this->event->relationLoaded('user')) {
            $this->event->load('user');
        }
        
        return [
            'event' => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'description' => $this->event->description,
                'start_date' => $this->event->start_date->toISOString(),
                'end_date' => $this->event->end_date->toISOString(),
                'venue' => $this->event->venue,
                'planned_budget' => $this->event->planned_budget,
                'approval_status' => $this->event->approval_status,
                'status' => $this->event->status,
                'images' => $this->event->images ?? [],
                'images_urls' => $this->event->images ? array_map(function($path) {
                    return storage_url($path);
                }, $this->event->images) : [],
                'created_at' => $this->event->created_at->toISOString(),
                'user' => [
                    'id' => $this->event->user->id,
                    'name' => $this->event->user->name,
                ],
            ]
        ];
    }
}
