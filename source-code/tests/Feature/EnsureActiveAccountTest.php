<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class EnsureActiveAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_credentials_cannot_start_a_session_from_either_login_route(): void
    {
        $user = $this->inactiveOsasAdmin();

        foreach (['/login', '/org/login'] as $uri) {
            $response = $this->postJson($uri, [
                'student_id' => $user->student_id,
                'password' => 'password',
            ]);

            $response
                ->assertUnprocessable()
                ->assertJsonPath('success', false)
                ->assertJsonPath('errors.student_id.0', 'This account is inactive.');
            $this->assertGuest();
        }
    }

    public function test_stale_inactive_osas_session_is_logged_out_before_role_authorization(): void
    {
        $user = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);

        $this->actingAs($user);
        User::query()->whereKey($user->id)->update(['is_active' => false]);

        $this->get('/osas/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_deleted_osas_session_is_treated_as_unauthenticated(): void
    {
        $user = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);

        $this->actingAs($user);
        User::query()->whereKey($user->id)->delete();

        $this->get('/osas/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_active_account_middleware_precedes_osas_role_checks(): void
    {
        $route = Route::getRoutes()->getByName('osas.dashboard');
        self::assertNotNull($route);

        $middleware = $route->gatherMiddleware();
        self::assertContains('active.account', $middleware);
        self::assertLessThan(
            array_search('role:osas_admin', $middleware, true),
            array_search('active.account', $middleware, true),
        );
    }

    public function test_expired_student_credentials_cannot_start_a_session_from_either_login_route(): void
    {
        $user = $this->expiredStudent();

        foreach (['/login', '/org/login'] as $uri) {
            $response = $this->postJson($uri, [
                'student_id' => $user->student_id,
                'password' => 'password',
            ]);

            $response
                ->assertUnprocessable()
                ->assertJsonPath('success', false)
                ->assertJsonPath(
                    'errors.student_id.0',
                    'This account has expired and can no longer access the system. Please contact OSAS.',
                );
            $this->assertGuest();
        }
    }

    public function test_a_student_inside_the_grace_window_can_still_log_in(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'expires_at' => now()->subMonth(),
            'password' => Hash::make('password'),
        ]);
        $organization = \App\Models\Organization::factory()->create([
            'status' => 'Approved',
            'has_system' => true,
        ]);
        \App\Models\OrganizationUser::createAssignment($user, $organization, 'Member', 'Active');

        $response = $this->post('/login', [
            'student_id' => $user->student_id,
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    private function inactiveOsasAdmin(): User
    {
        return User::factory()->create([
            'role' => 'osas_admin',
            'is_active' => false,
            'password' => Hash::make('password'),
        ]);
    }

    private function expiredStudent(): User
    {
        return User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'expires_at' => now()->subMonths(3),
            'password' => Hash::make('password'),
        ]);
    }
}
