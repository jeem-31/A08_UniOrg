<?php

namespace Tests\Unit;

use App\Events\OsasProposalQueueUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Tests\TestCase;

final class OsasProposalQueueUpdatedTest extends TestCase
{
    public function test_it_broadcasts_the_current_queue_count_on_the_private_osas_channel(): void
    {
        $event = new OsasProposalQueueUpdated(3);

        self::assertInstanceOf(ShouldBroadcastNow::class, $event);
        self::assertInstanceOf(PrivateChannel::class, $event->broadcastOn()[0]);
        self::assertSame('private-osas-proposals', $event->broadcastOn()[0]->name);
        self::assertSame('osas-proposal-queue-updated', $event->broadcastAs());
        self::assertSame(['pending_proposals_count' => 3], $event->broadcastWith());
    }
}
