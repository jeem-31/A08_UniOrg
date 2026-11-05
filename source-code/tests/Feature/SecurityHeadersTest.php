<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_headers_are_present_on_success_and_error_responses(): void
    {
        foreach (['/health' => 200, '/missing-security-test-page' => 404] as $path => $status) {
            $this->get($path)
                ->assertStatus($status)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeaderMissing('Strict-Transport-Security');
        }
    }

    public function test_hsts_is_only_set_for_secure_production_requests_including_railway_proxy_requests(): void
    {
        $this->app->instance('env', 'production');
        $this->get('http://localhost/health')->assertHeaderMissing('Strict-Transport-Security');

        $this->withServerVariables(['HTTP_X_FORWARDED_PROTO' => 'https'])
            ->get('http://localhost/health')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_download_response_keeps_its_content_headers(): void
    {
        Route::middleware('web')->get('/security-test-download', static fn () => response()
            ->streamDownload(static function (): void { echo 'sample'; }, 'sample.txt'));

        $this->get('/security-test-download')
            ->assertOk()
            ->assertDownload('sample.txt')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
