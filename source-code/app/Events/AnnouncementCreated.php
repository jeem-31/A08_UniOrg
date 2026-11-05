<?php

namespace App\Events;

use App\Models\Announcement;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnnouncementCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public $announcement;

    /**
     * Create a new event instance.
     */
    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
        $this->broadcastVia('pusher');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('announcements'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'announcement-created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        // Load relationships if not loaded (skip pivot query for unsaved models, e.g. unit tests)
        if (!$this->announcement->relationLoaded('user')) {
            $this->announcement->load('user');
        }
        $targetIds = [];
        if ($this->announcement->relationLoaded('targetOrganizations')) {
            $targetIds = $this->announcement->targetOrganizations->pluck('id')->all();
        } elseif ($this->announcement->exists) {
            try {
                $this->announcement->load('targetOrganizations');
                $targetIds = $this->announcement->targetOrganizations->pluck('id')->all();
            } catch (\Throwable $e) {
                $targetIds = [];
            }
        }
        
        return [
            'announcement' => [
                'id' => $this->announcement->id,
                'organization_id' => $this->announcement->organization_id,
                'title' => $this->announcement->title,
                'content' => $this->announcement->content,
                'type' => $this->announcement->type,
                'status' => $this->announcement->status,
                'visibility' => $this->announcement->visibility ?? 'all',
                'target_organization_ids' => $targetIds,
                'images' => $this->announcement->images ?? [],
                'images_urls' => $this->announcement->images ? array_map(function($path) {
                    return storage_url($path);
                }, $this->announcement->images) : [],
                'created_at' => $this->announcement->created_at->toISOString(),
                'user' => [
                    'id' => $this->announcement->user->id,
                    'name' => $this->announcement->user->name,
                ],
            ]
        ];
    }
}
