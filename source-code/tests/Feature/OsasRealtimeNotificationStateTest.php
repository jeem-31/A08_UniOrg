<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Middleware\EnsureActiveAccount;
use App\Services\OsasProposalQueueService;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\RealBroadcastAuth;
use Tests\TestCase;

final class OsasRealtimeNotificationStateTest extends TestCase
{
    use RealBroadcastAuth;

    public function test_osas_admin_receives_a_fresh_private_notification_state(): void
    {
        $user = $this->user([
            'role' => 'osas_admin',
            'is_active' => true,
        ]);
        $notification = (new DatabaseNotification)->forceFill([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\EventSubmittedToOsasNotification',
            'data' => [
                'title' => 'New Event Submission',
                'message' => 'Test event is ready for OSAS review.',
                'icon' => 'ph-paper-plane-tilt',
                'color' => 'blue',
                'url' => '/osas/proposals',
            ],
            'read_at' => null,
            'created_at' => now(),
        ]);
        $aggregate = (new DatabaseNotification)->forceFill([
            'total_count' => 1,
            'unread_count' => 1,
        ]);
        $relation = Mockery::mock(MorphMany::class);
        $relation->shouldReceive('reorder')->once()->ordered()->andReturnSelf();
        $relation->shouldReceive('selectRaw')->twice()->ordered()->andReturnSelf();
        $relation->shouldReceive('first')->once()->ordered()->andReturn($aggregate);
        $relation->shouldReceive('orderByDesc')->once()->with('created_at')->andReturnSelf();
        $relation->shouldReceive('limit')->once()->with(10)->andReturnSelf();
        $relation->shouldReceive('get')->once()->andReturn(new Collection([$notification]));
        $user->notificationRelation = $relation;

        $queue = Mockery::mock(OsasProposalQueueService::class);
        $queue->shouldReceive('pendingCount')->once()->andReturn(0);
        $this->app->instance(OsasProposalQueueService::class, $queue);

        $response = $this->withoutMiddleware(EnsureActiveAccount::class)
            ->actingAs($user)
            ->getJson(route('osas.notifications.state'));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('pending_proposals_count', 0)
            ->assertJsonStructure(['dropdown_html']);
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('New Event Submission', (string) $response->json('dropdown_html'));
    }

    public function test_non_osas_users_cannot_read_realtime_notification_state(): void
    {
        $student = $this->user([
            'role' => 'student',
            'is_active' => true,
        ]);

        $this->withoutMiddleware(EnsureActiveAccount::class)
            ->actingAs($student)
            ->getJson(route('osas.notifications.state'))
            ->assertForbidden();
    }

    public function test_only_osas_admins_can_authorize_the_private_proposal_channel(): void
    {
        $this->useRealBroadcastAuth();

        $osas = $this->user(['role' => 'osas_admin', 'is_active' => true]);
        $student = $this->user(['role' => 'student', 'is_active' => true]);
        $payload = [
            'channel_name' => 'private-osas-proposals',
            'socket_id' => '1234.5678',
        ];

        $this->actingAs($osas)
            ->postJson('/broadcasting/auth', $payload)
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->actingAs($student)
            ->postJson('/broadcasting/auth', $payload)
            ->assertForbidden();
    }

    /** @param array<string, mixed> $attributes */
    private function user(array $attributes): RealtimeStateUser
    {
        return (new RealtimeStateUser)->forceFill([
            'id' => 42,
            'first_name' => 'OSAS',
            'last_name' => 'Tester',
            'email' => 'osas@example.test',
            ...$attributes,
        ]);
    }
}

final class RealtimeStateUser extends User
{
    public MorphMany $notificationRelation;

    public function notifications(): MorphMany
    {
        return $this->notificationRelation;
    }
}
