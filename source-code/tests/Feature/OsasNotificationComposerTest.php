<?php

namespace Tests\Feature;

use App\Http\View\Composers\OsasNotificationComposer;
use App\Models\User;
use App\Services\OsasNotificationService;
use App\Services\OsasProposalQueueService;
use App\ViewModels\ResolvedNotificationItem;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Mockery;
use Tests\TestCase;

class OsasNotificationComposerTest extends TestCase
{
    public function test_registered_composer_supplies_resolved_recent_items_and_counts_for_osas_admin(): void
    {
        $admin = $this->user('osas_admin');
        $notifications = $this->notifications(10);
        $ids = $notifications->pluck('id')->all();
        $relation = Mockery::mock(MorphMany::class);
        $relation->shouldReceive('reorder')->once()->andReturnSelf();
        $relation->shouldReceive('selectRaw')->twice()->andReturnSelf();
        $relation->shouldReceive('first')->once()->andReturn(
            (new DatabaseNotification)->forceFill([
                'total_count' => 12,
                'unread_count' => 3,
            ]),
        );
        $relation->shouldReceive('orderByDesc')->once()->with('created_at')->andReturnSelf();
        $relation->shouldReceive('limit')->once()->with(10)->andReturnSelf();
        $relation->shouldReceive('get')->once()->andReturn($notifications);
        $admin->notificationRelation = $relation;
        $request = $this->bindRequest($admin);
        $proposalQueue = Mockery::mock(OsasProposalQueueService::class);
        $proposalQueue->shouldReceive('pendingCount')->once()->andReturn(4);
        self::assertSame('osas_admin', $admin->role);
        self::assertSame($admin, $request->user());
        self::assertInstanceOf(User::class, $request->user());

        $view = Mockery::mock(View::class);
        $view->shouldReceive('with')->once()->with(Mockery::on(
            function (array $data) use ($ids): bool {
                $items = $data['osasDropdownNotifications'] ?? null;

                return $items?->count() === 10
                    && $items->every(fn (mixed $item): bool => $item instanceof ResolvedNotificationItem)
                    && $items->pluck('id')->all() === $ids
                    && $data['totalCount'] === 12
                    && $data['unreadCount'] === 3
                    && $data['pendingProposalsCount'] === 4;
            },
        ));

        (new OsasNotificationComposer(
            $this->app->make(OsasNotificationService::class),
            $proposalQueue,
            $request,
        ))->compose($view);
    }

    public function test_guest_and_non_osas_rendering_paths_do_not_query_notifications(): void
    {
        foreach ([null, $this->user('student')] as $user) {
            $request = $this->bindRequest($user);
            $proposalQueue = Mockery::mock(OsasProposalQueueService::class);
            $proposalQueue->shouldNotReceive('pendingCount');

            $view = Mockery::mock(View::class);
            $view->shouldNotReceive('with');

            (new OsasNotificationComposer(
                $this->app->make(OsasNotificationService::class),
                $proposalQueue,
                $request,
            ))->compose($view);
        }
    }

    public function test_composer_is_not_registered_for_unrelated_layouts(): void
    {
        $admin = $this->user('osas_admin');
        $this->bindRequest($admin);

        self::assertNotEmpty($this->app['events']->getListeners('composing: layouts.osas'));

        $view = Mockery::mock(View::class);
        $view->shouldNotReceive('with');
        $this->app['events']->dispatch('composing: layouts.unrelated', [$view]);
    }

    private function user(string $role): ComposerTestUser
    {
        return (new ComposerTestUser)->forceFill([
            'id' => 42,
            'role' => $role,
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);
    }

    private function bindRequest(?User $user): Request
    {
        $request = Request::create('/osas/dashboard', 'GET');
        $this->app->instance('request', $request);
        $this->app->instance(Request::class, $request);
        $request->setUserResolver(fn (): ?User => $user);

        return $request;
    }

    /** @return Collection<int, DatabaseNotification> */
    private function notifications(int $count): Collection
    {
        return collect(range(0, $count - 1))->map(
            fn (int $index): DatabaseNotification => (new DatabaseNotification)->forceFill([
                'id' => (string) Str::uuid(),
                'type' => 'Tests\\Notification',
                'data' => ['message' => "Notification {$index}"],
                'read_at' => $index < 3 ? null : now()->subMinute(),
                'created_at' => now()->subMinutes($index),
                'updated_at' => now()->subMinutes($index),
            ]),
        );
    }
}

final class ComposerTestUser extends User
{
    public MorphMany $notificationRelation;

    public function notifications()
    {
        return $this->notificationRelation;
    }
}
