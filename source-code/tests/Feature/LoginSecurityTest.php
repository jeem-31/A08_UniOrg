<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_the_same_error_for_an_unknown_id_and_a_wrong_password(): void
    {
        Cache::flush();

        User::factory()->create([
            'student_id' => 'EXISTING-001',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        foreach (['/login', '/org/login'] as $uri) {
            $this->postJson($uri, [
                'student_id' => 'EXISTING-001',
                'password' => 'wrong-password',
            ])
                ->assertUnprocessable()
                ->assertJsonPath('errors.student_id.0', 'The provided ID or password is incorrect.');

            $this->postJson($uri, [
                'student_id' => 'MISSING-001',
                'password' => 'wrong-password',
            ])
                ->assertUnprocessable()
                ->assertJsonPath('errors.student_id.0', 'The provided ID or password is incorrect.');
        }
    }

    public function test_both_login_routes_are_throttled_after_five_failed_attempts(): void
    {
        Cache::flush();

        foreach (['/login', '/org/login'] as $uri) {
            $studentId = 'RATE-'.substr(md5($uri), 0, 8);

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this->postJson($uri, [
                    'student_id' => $studentId,
                    'password' => 'wrong-password',
                ])->assertUnprocessable();
            }

            $this->postJson($uri, [
                'student_id' => $studentId,
                'password' => 'wrong-password',
            ])
                ->assertStatus(429)
                ->assertJsonPath('success', false)
                ->assertJsonPath('errors.student_id.0', 'Too many login attempts. Please try again later.');
        }
    }
}
