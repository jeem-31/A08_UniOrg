<?php

namespace Tests\Feature;

use App\Models\AuthenticationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordRecoveryRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_code_requests_share_an_email_limit_across_casing_and_ip_addresses(): void
    {
        foreach (['missing@example.com', 'MISSING@example.com', 'Missing@example.com'] as $index => $email) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($index + 1)])
                ->postJson('/forgot-password', ['email' => $email])->assertNotFound();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.9'])
            ->postJson('/forgot-password', ['email' => 'missing@example.com'])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonStructure(['error', 'message']);

        $this->assertDatabaseHas('authentication_events', ['event' => 'password-email.throttled']);
    }

    public function test_reset_attempts_are_limited_before_a_password_can_be_changed(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email, 'token' => '654321', 'created_at' => now(),
        ]);

        $payload = [
            'email' => $user->email,
            'code' => '000000',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/reset-password', $payload)->assertStatus(400);
        }

        $this->postJson('/reset-password', array_replace($payload, ['code' => '654321']))
            ->assertStatus(429)->assertHeader('Retry-After');

        self::assertSame($user->password, $user->fresh()->password);
        $this->assertDatabaseHas('authentication_events', ['event' => 'password-reset.throttled']);
    }

    public function test_rotating_email_addresses_still_hits_the_ip_limit(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson('/forgot-password', ['email' => 'missing'.$attempt.'@example.com'])
                ->assertNotFound();
        }

        $this->postJson('/forgot-password', ['email' => 'another@example.com'])->assertStatus(429);
        self::assertSame(1, AuthenticationEvent::where('event', 'password-email.throttled')->count());
    }

    public function test_cooldown_expires_and_a_legitimate_reset_still_works(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        Mail::shouldReceive('raw')->times(4)->andReturnNull();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();
        }

        $this->postJson('/forgot-password', ['email' => $user->email])->assertStatus(429);
        $this->travel(16)->minutes();
        $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();

        $code = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->postJson('/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        self::assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }
}
