<?php

namespace Tests\Unit;

use App\Events\AnnouncementCreated;
use App\Events\AnnouncementDeleted;
use App\Events\AnnouncementUpdated;
use App\Models\Announcement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Tests\TestCase;

class AnnouncementBroadcastEventTest extends TestCase
{
    public function test_created_and_updated_events_use_pusher_with_organization_scope(): void
    {
        $announcement = $this->announcement();

        foreach ([
            new AnnouncementCreated($announcement),
            new AnnouncementUpdated($announcement),
        ] as $event) {
            $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
            $this->assertSame(['pusher'], $event->broadcastConnections());
            $this->assertSame('announcements', $event->broadcastOn()[0]->name);
            $this->assertSame(42, $event->broadcastWith()['announcement']['organization_id']);
        }
    }

    public function test_deleted_event_broadcasts_the_organization_scope(): void
    {
        $event = new AnnouncementDeleted(123, 42);

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
        $this->assertSame(['pusher'], $event->broadcastConnections());
        $this->assertSame('announcement-deleted', $event->broadcastAs());
        $this->assertSame([
            'id' => 123,
            'organization_id' => 42,
        ], $event->broadcastWith());
    }

    private function announcement(): Announcement
    {
        $author = new User();
        $author->id = 7;
        $author->name = 'Announcement Author';

        $announcement = new Announcement([
            'organization_id' => 42,
            'title' => 'Pusher pilot',
            'content' => 'Announcement body',
            'type' => 'general',
            'status' => 'active',
            'images' => [],
        ]);
        $announcement->id = 123;
        $announcement->created_at = Carbon::parse('2026-08-02 10:00:00');
        $announcement->setRelation('user', $author);

        return $announcement;
    }
}
