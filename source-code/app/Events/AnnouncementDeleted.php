<?php

namespace App\Events;

use App\Models\Announcement;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnnouncementDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public $announcementId;
    public $organizationId;

    public function __construct($announcementId, $organizationId)
    {
        $this->announcementId = $announcementId;
        $this->organizationId = $organizationId;
        $this->broadcastVia('pusher');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('announcements'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'announcement-deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->announcementId,
            'organization_id' => $this->organizationId,
        ];
    }
}
