<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OsasNotificationService;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;
use Mockery;
use Tests\TestCase;

class OsasNotificationServiceCountsTest extends TestCase
{
    public function test_counts_clears_existing_relation_order_before_user_scoped_aggregation(): void
    {
        $aggregate = (new DatabaseNotification)->forceFill([
            'total_count' => 3,
            'unread_count' => 2,
        ]);
        $relation = Mockery::mock(MorphMany::class);
        $relation->shouldReceive('reorder')->once()->ordered()->andReturnSelf();
        $relation->shouldReceive('selectRaw')
            ->once()
            ->with('COUNT(*) AS total_count')
            ->ordered()
            ->andReturnSelf();
        $relation->shouldReceive('selectRaw')
            ->once()
            ->with('COUNT(CASE WHEN read_at IS NULL THEN 1 END) AS unread_count')
            ->ordered()
            ->andReturnSelf();
        $relation->shouldReceive('first')->once()->ordered()->andReturn($aggregate);

        $user = (new OrderedNotificationsUser)->forceFill(['id' => 42]);
        $user->notificationRelation = $relation;

        $counts = $this->app->make(OsasNotificationService::class)->counts($user);

        self::assertSame(3, $counts->total);
        self::assertSame(2, $counts->unread);
        self::assertGreaterThanOrEqual(0, $counts->unread);
        self::assertLessThanOrEqual($counts->total, $counts->unread);
    }
}
final class OrderedNotificationsUser extends User
{
    public MorphMany $notificationRelation;

    public function notifications(): MorphMany
    {
        return $this->notificationRelation;
    }
}
