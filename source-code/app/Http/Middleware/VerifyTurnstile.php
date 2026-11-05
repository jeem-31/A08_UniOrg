<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstile
{
    public function handle(Request $request, Closure $next, string $action): Response
    {
        if (! config('services.turnstile.enabled')) {
            return $next($request);
        }

        $secret = config('services.turnstile.secret_key');
        $hostnames = config('services.turnstile.hostnames', []);

        if (! $secret || ! config('services.turnstile.site_key') || empty($hostnames)) {
            return $this->reject($request, 'Security verification is unavailable. Please try again later.', 503);
        }

        $token = $request->input('cf-turnstile-response');
        if (! is_string($token) || trim($token) === '' || strlen($token) > 2048) {
            return $this->reject($request, 'Please complete the security verification.');
        }

        try {
            $response = Http::asForm()->acceptJson()->connectTimeout(3)->timeout(8)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                ]);
        } catch (ConnectionException) {
            return $this->reject($request, 'Security verification is unavailable. Please try again later.', 503);
        }

        if (! $response->successful()) {
            return $this->reject($request, 'Security verification is unavailable. Please try again later.', 503);
        }

        $result = $response->json();
        if (! is_array($result)
            || ($result['success'] ?? false) !== true
            || ! in_array($result['hostname'] ?? null, $hostnames, true)
            || ($result['action'] ?? null) !== $action) {
            return $this->reject($request, 'Security verification failed or expired. Please try again.');
        }

        return $next($request);
    }

    private function reject(Request $request, string $message, int $status = 422): Response
    {
        $errors = ['cf-turnstile-response' => [$message]];

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => $message,
                'message' => $message,
                'errors' => $errors,
            ], $status);
        }

        return back()->withErrors($errors)->onlyInput('student_id', 'email');
    }
}
