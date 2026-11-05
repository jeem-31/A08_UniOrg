<?php

namespace Tests\Feature;

use App\Models\AuthenticationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        config()->set('services.turnstile', [
            'enabled' => true,
            'site_key' => 'public-test-site-key',
            'secret_key' => 'private-test-secret-key',
            'hostnames' => ['uniorg.example'],
        ]);
    }

    public function test_all_protected_entry_points_reject_missing_or_malformed_tokens_before_side_effects(): void
    {
        Http::fake();
        Mail::fake();
        $user = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
        $payload = [
            'student_id' => $user->student_id,
            'email' => $user->email,
            'password' => 'Pass1234',
            'password_confirmation' => 'Pass1234',
            'code' => '123456',
        ];

        foreach (['/login', '/org/login', '/forgot-password', '/reset-password', '/webauthn/login/options'] as $path) {
            Cache::flush();
            foreach ([null, ['array-token'], str_repeat('x', 2049)] as $token) {
                $this->postJson($path, $payload + ['cf-turnstile-response' => $token])
                    ->assertUnprocessable()
                    ->assertJsonPath('success', false)
                    ->assertJsonStructure(['error', 'errors' => ['cf-turnstile-response']]);
            }
        }

        $this->assertGuest();
        self::assertSame($user->password, $user->fresh()->password);
        self::assertSame(0, DB::table('password_reset_tokens')->count());
        self::assertSame(0, AuthenticationEvent::count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_a_verified_request_can_log_in_through_either_password_route(): void
    {
        $this->fakeSuccess('login');
        $user = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);

        foreach (['/login', '/org/login'] as $path) {
            $this->postJson($path, [
                'student_id' => $user->student_id,
                'password' => 'Pass1234',
                'cf-turnstile-response' => 'fresh-token-'.md5($path),
            ])->assertOk()->assertJsonPath('success', true);
            $this->assertAuthenticatedAs($user);
            Auth::logout();
        }

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === self::VERIFY_URL
            && $request['secret'] === 'private-test-secret-key'
            && str_starts_with($request['response'], 'fresh-token-'));
        self::assertSame(0, AuthenticationEvent::count());
    }

    public function test_verified_recovery_can_send_a_code_and_reset_the_password_with_a_new_token(): void
    {
        Mail::shouldReceive('raw')->once()->andReturnNull();
        $user = User::factory()->create();
        Http::fake([self::VERIFY_URL => Http::sequence()
            ->push(['success' => true, 'hostname' => 'uniorg.example', 'action' => 'password_email'])
            ->push(['success' => true, 'hostname' => 'uniorg.example', 'action' => 'password_reset'])]);

        $this->postJson('/forgot-password', [
            'email' => $user->email,
            'cf-turnstile-response' => 'send-code-token',
        ])->assertOk();

        $code = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        self::assertNotNull($code);

        $this->postJson('/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'cf-turnstile-response' => 'reset-password-token',
        ])->assertOk();

        self::assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_failed_expired_wrong_hostname_wrong_action_and_malformed_responses_are_rejected(): void
    {
        $results = [
            ['success' => false, 'error-codes' => ['invalid-input-response']],
            ['success' => false, 'error-codes' => ['timeout-or-duplicate']],
            ['success' => true, 'hostname' => 'attacker.example', 'action' => 'login'],
            ['success' => true, 'hostname' => 'uniorg.example', 'action' => 'password_email'],
            ['success' => 'true', 'hostname' => 'uniorg.example', 'action' => 'login'],
            [],
            'not-json',
        ];
        $sequence = Http::sequence();
        foreach ($results as $result) {
            $sequence->push($result);
        }
        Http::fake([self::VERIFY_URL => $sequence]);
        foreach ($results as $result) {
            Cache::flush();
            $this->postJson('/org/login', [
                'student_id' => 'TEST-ID',
                'password' => 'NeverStoreThisPassword',
                'cf-turnstile-response' => 'NeverStoreThisToken',
            ])->assertUnprocessable()->assertJsonPath('success', false)
                ->assertDontSee('private-test-secret-key')->assertDontSee('NeverStoreThisToken');
        }

        self::assertSame(0, AuthenticationEvent::count());
        $this->assertGuest();
    }

    public function test_a_previously_consumed_token_is_not_accepted_on_a_second_request(): void
    {
        Http::fake([self::VERIFY_URL => Http::sequence()
            ->push(['success' => true, 'hostname' => 'uniorg.example', 'action' => 'login'])
            ->push(['success' => false, 'error-codes' => ['timeout-or-duplicate']])]);

        $payload = ['student_id' => 'UNKNOWN', 'password' => 'wrong', 'cf-turnstile-response' => 'same-token'];
        $this->postJson('/org/login', $payload)->assertJsonValidationErrors('student_id');
        $this->postJson('/org/login', $payload)->assertJsonValidationErrors('cf-turnstile-response');

        Http::assertSentCount(2);
        self::assertSame(1, AuthenticationEvent::where('event', 'login.failed')->count());
    }

    public function test_verification_service_failures_block_login_without_exposing_details(): void
    {
        $failures = [
            fn () => throw new ConnectionException('Secret connection details'),
            fn () => Http::response('Secret upstream details', 503),
        ];
        Http::fake([self::VERIFY_URL => function () use (&$failures) { return array_shift($failures)(); }]);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->postJson('/org/login', [
                'student_id' => 'TEST-ID', 'password' => 'wrong', 'cf-turnstile-response' => 'token',
            ])->assertStatus(503)->assertJsonPath('success', false)->assertDontSee('Secret');
        }

        self::assertSame(0, AuthenticationEvent::count());
        $this->assertGuest();
    }

    public function test_incomplete_enabled_configuration_does_not_bypass_verification(): void
    {
        Http::fake();
        foreach (['secret_key' => null, 'site_key' => null, 'hostnames' => []] as $setting => $missing) {
            $original = config('services.turnstile.'.$setting);
            config()->set('services.turnstile.'.$setting, $missing);
            $this->postJson('/org/login', ['student_id' => 'TEST-ID', 'password' => 'wrong'])
                ->assertStatus(503);
            config()->set('services.turnstile.'.$setting, $original);
        }
        Http::assertNothingSent();
    }

    public function test_existing_login_rate_limit_runs_before_calls_to_cloudflare(): void
    {
        $this->fakeSuccess('login');
        $payload = ['student_id' => 'MISSING', 'password' => 'wrong', 'cf-turnstile-response' => 'new-token'];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/org/login', $payload)->assertJsonValidationErrors('student_id');
        }
        $this->postJson('/org/login', $payload)->assertStatus(429);

        Http::assertSentCount(5);
        self::assertSame(5, AuthenticationEvent::where('event', 'login.failed')->count());
        self::assertSame(1, AuthenticationEvent::where('event', 'login.throttled')->count());
    }

    public function test_fingerprint_options_require_verification_before_creating_a_challenge(): void
    {
        $this->fakeSuccess('login');
        $this->postJson('/webauthn/login/options', ['cf-turnstile-response' => 'fingerprint-token'])
            ->assertOk()->assertJsonPath('success', true)
            ->assertSessionHas('webauthn_login_challenge');
        Http::assertSentCount(1);
    }

    public function test_non_ajax_rejection_does_not_flash_credentials_or_verification_tokens(): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['success' => false])]);
        $this->from('/org/login')->post('/org/login', [
            'student_id' => 'TEST-ID', 'password' => 'PrivatePassword123!', 'code' => '123456',
            'cf-turnstile-response' => 'PrivateToken',
        ])->assertRedirect('/org/login')->assertSessionHasErrors('cf-turnstile-response')
            ->assertSessionHasInput('student_id', 'TEST-ID');

        self::assertSame(['student_id' => 'TEST-ID'], session()->getOldInput());
    }

    public function test_widgets_only_render_when_enabled_and_the_secret_is_never_rendered(): void
    {
        foreach (['/login', '/org/login'] as $path) {
            $this->get($path)->assertOk()->assertSee('public-test-site-key')
                ->assertSee('data-action="login"', false)
                ->assertSee('data-action="password_email"', false)
                ->assertSee('data-action="password_reset"', false)
                ->assertSee('challenges.cloudflare.com/turnstile/v0/api.js', false)
                ->assertDontSee('private-test-secret-key');
        }

        config()->set('services.turnstile.enabled', false);
        Http::fake();
        $this->get('/org/login')->assertOk()->assertDontSee('data-turnstile', false)
            ->assertDontSee('challenges.cloudflare.com/turnstile/v0/api.js', false);
        $this->postJson('/org/login', ['student_id' => 'MISSING', 'password' => 'wrong'])
            ->assertJsonValidationErrors('student_id');
        Http::assertNothingSent();
    }

    private function fakeSuccess(string $action): void
    {
        Http::fake([self::VERIFY_URL => Http::response([
            'success' => true, 'hostname' => 'uniorg.example', 'action' => $action,
        ])]);
    }
}
