<?php

namespace Tests\Feature;

use App\Models\AuthenticationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthenticationActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_both_login_routes_record_failures_without_storing_credentials(): void
    {
        foreach (['/login', '/org/login'] as $path) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])->postJson($path, [
                'student_id' => 'MISSING-STUDENT',
                'password' => 'NeverStoreThisPassword!',
                'otp' => 'NeverStoreThisCode',
                'token' => 'NeverStoreThisToken',
            ])->assertUnprocessable();

            $event = AuthenticationEvent::latest('id')->firstOrFail();
            self::assertSame('login.failed', $event->event);
            self::assertSame('MISSING-STUDENT', $event->identifier);
            self::assertSame('192.0.2.20', $event->ip_address);
            self::assertSame($path, $event->path);
            self::assertStringNotContainsString('NeverStore', $event->toJson());
        }

        self::assertSame(2, AuthenticationEvent::count());
    }

    public function test_a_blocked_login_is_recorded_once_as_a_rate_limit_hit(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/org/login', ['student_id' => 'MISSING', 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->postJson('/org/login', ['student_id' => 'MISSING', 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('X-Content-Type-Options', 'nosniff');

        self::assertSame(5, AuthenticationEvent::where('event', 'login.failed')->count());
        self::assertSame(1, AuthenticationEvent::where('event', 'login.throttled')->count());
    }

    public function test_a_successful_osas_login_is_not_reported_as_a_failure(): void
    {
        $user = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
        $this->postJson('/org/login', ['student_id' => $user->student_id, 'password' => 'Pass1234'])
            ->assertOk()->assertJsonPath('success', true);

        self::assertSame(0, AuthenticationEvent::count());
    }

    public function test_only_osas_can_view_security_activity_and_identifiers_are_escaped(): void
    {
        AuthenticationEvent::create([
            'event' => 'login.failed',
            'identifier' => '<script>alert(1)</script>',
            'ip_address' => '192.0.2.99',
            'path' => '/org/login',
            'occurred_at' => now(),
        ]);

        $this->get('/osas/settings?tab=audit-logs')->assertRedirect(route('login'));
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($student)->get('/osas/settings?tab=audit-logs')->assertForbidden();

        $osas = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
        $this->actingAs($osas)->get('/osas/settings?tab=audit-logs')
            ->assertOk()
            ->assertSee('Login Security')
            ->assertSee('192.0.2.99')
            ->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_osas_can_select_failed_logins_or_login_rate_limit_hits(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'osas_admin', 'is_active' => true]));
        $this->recordActivity('FAILED-ID', 'login.failed', '2026-10-21 09:00:00');
        $this->recordActivity('THROTTLED-ID', 'login.throttled', '2026-10-21 09:01:00');
        $this->recordActivity('RECOVERY-ID', 'password-email.throttled', '2026-10-21 09:02:00');

        foreach (['login.failed' => 'FAILED-ID', 'login.throttled' => 'THROTTLED-ID'] as $event => $identifier) {
            $this->get(route('osas.settings', ['tab' => 'audit-logs', 'security_event' => $event]))
                ->assertOk()
                ->assertViewHas('authenticationEvents', fn ($events) => $events->pluck('identifier')->all() === [$identifier])
                ->assertViewHas('authenticationFilters', fn ($filters) => $filters['security_event'] === $event);
        }

        $this->get(route('osas.settings', ['tab' => 'audit-logs']))
            ->assertOk()
            ->assertViewHas('authenticationEvents', fn ($events) => $events->count() === 3);
    }

    public function test_activity_and_manila_date_time_filters_are_combined_with_inclusive_minutes(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'osas_admin', 'is_active' => true]));
        $this->recordActivity('BEFORE', 'login.failed', '2026-10-21 08:59:59');
        $this->recordActivity('START', 'login.failed', '2026-10-21 09:00:00');
        $this->recordActivity('MIDDLE', 'login.failed', '2026-10-21 09:15:00');
        $this->recordActivity('END', 'login.failed', '2026-10-21 09:30:59');
        $this->recordActivity('AFTER', 'login.failed', '2026-10-21 09:31:00');
        $this->recordActivity('OTHER-TYPE', 'login.throttled', '2026-10-21 09:20:00');

        $filters = [
            'tab' => 'audit-logs',
            'security_event' => 'login.failed',
            'security_from' => '2026-10-21T09:00',
            'security_to' => '2026-10-21T09:30',
        ];
        $this->get(route('osas.settings', $filters))
            ->assertOk()
            ->assertViewHas('authenticationEvents', fn ($events) => $events->pluck('identifier')->all() === ['END', 'MIDDLE', 'START'])
            ->assertSee('Asia/Manila')
            ->assertSee('Oct 21, 2026 09:30:59 AM')
            ->assertSee('value="2026-10-21T09:00"', false)
            ->assertSee('value="2026-10-21T09:30"', false)
            ->assertSee('href="'.e(route('osas.settings', $filters)).'"', false)
            ->assertSee('href="'.e(route('osas.settings', ['tab' => 'audit-logs'])).'"', false);
    }

    public function test_date_time_filters_allow_either_boundary_to_be_omitted(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'osas_admin', 'is_active' => true]));
        $this->recordActivity('OLDER', 'login.failed', '2026-10-20 09:00:00');
        $this->recordActivity('TODAY', 'login.failed', '2026-10-21 09:00:30');
        $this->recordActivity('NEWER', 'login.failed', '2026-10-22 09:00:00');

        $this->get(route('osas.settings', ['tab' => 'audit-logs', 'security_from' => '2026-10-21T09:00']))
            ->assertOk()
            ->assertViewHas('authenticationEvents', fn ($events) => $events->pluck('identifier')->all() === ['NEWER', 'TODAY']);
        $this->get(route('osas.settings', ['tab' => 'audit-logs', 'security_to' => '2026-10-21T09:00']))
            ->assertOk()
            ->assertViewHas('authenticationEvents', fn ($events) => $events->pluck('identifier')->all() === ['TODAY', 'OLDER']);
    }

    public function test_filters_are_applied_before_the_latest_fifty_limit(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'osas_admin', 'is_active' => true]));
        $this->recordActivity('OLDER-MATCH', 'login.throttled', '2026-10-20 09:00:00');
        for ($index = 0; $index < 60; $index++) {
            $this->recordActivity('NEWER-'.$index, 'login.failed', '2026-10-21 09:00:'.str_pad((string) $index, 2, '0', STR_PAD_LEFT));
        }

        $this->get(route('osas.settings', ['tab' => 'audit-logs', 'security_event' => 'login.throttled']))
            ->assertOk()
            ->assertViewHas('authenticationEvents', fn ($events) => $events->pluck('identifier')->all() === ['OLDER-MATCH']);
        $this->get(route('osas.settings', ['tab' => 'audit-logs', 'security_event' => 'login.failed']))
            ->assertOk()
            ->assertViewHas('authenticationEvents', fn ($events) => $events->count() === 50
                && $events->first()->identifier === 'NEWER-59'
                && $events->last()->identifier === 'NEWER-10');
    }

    public function test_invalid_filters_show_inline_errors_instead_of_unfiltered_activity(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'osas_admin', 'is_active' => true]));
        $this->recordActivity('EXISTING', 'login.failed', '2026-10-21 09:00:00');
        $invalidFilters = [
            [['security_event' => 'unsupported'], 'security_event'],
            [['security_event' => ['login.failed']], 'security_event'],
            [['security_from' => '2026-02-30T09:00'], 'security_from'],
            [['security_to' => ['not-a-date']], 'security_to'],
            [['security_from' => '2026-10-21T10:00', 'security_to' => '2026-10-21T09:00'], 'security_to'],
        ];

        foreach ($invalidFilters as [$filters, $errorField]) {
            $this->get(route('osas.settings', array_merge(['tab' => 'audit-logs'], $filters)))
                ->assertOk()
                ->assertViewHas('authenticationEvents', fn ($events) => $events->isEmpty())
                ->assertViewHas('authenticationFilterErrors', fn ($errors) => $errors->has($errorField))
                ->assertSee('Update the filters above to view login security activity.');
        }
    }

    public function test_no_matching_activity_shows_a_clear_empty_state(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'osas_admin', 'is_active' => true]));
        $this->recordActivity('EXISTING', 'login.failed', '2026-10-21 09:00:00');

        $this->get(route('osas.settings', ['tab' => 'audit-logs', 'security_from' => '2027-01-01T00:00']))
            ->assertOk()
            ->assertViewHas('authenticationEvents', fn ($events) => $events->isEmpty())
            ->assertSee('No login security activity matches these filters.');
    }

    private function recordActivity(string $identifier, string $event, string $occurredAt): void
    {
        AuthenticationEvent::create([
            'event' => $event,
            'identifier' => $identifier,
            'ip_address' => '192.0.2.99',
            'path' => '/org/login',
            'occurred_at' => $occurredAt,
        ]);
    }
}
