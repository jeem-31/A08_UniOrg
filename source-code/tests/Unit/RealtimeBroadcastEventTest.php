<?php

namespace Tests\Unit;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Tests\TestCase;

class RealtimeBroadcastEventTest extends TestCase
{
    public function test_all_realtime_events_broadcast_immediately(): void
    {
        $eventClasses = collect(glob(app_path('Events/*.php')))
            ->map(fn (string $path) => 'App\\Events\\'.pathinfo($path, PATHINFO_FILENAME));

        $this->assertNotEmpty($eventClasses);

        foreach ($eventClasses as $eventClass) {
            $this->assertTrue(
                is_subclass_of($eventClass, ShouldBroadcastNow::class),
                "{$eventClass} must broadcast immediately so Pusher does not depend on a queue worker."
            );
        }
    }
}
