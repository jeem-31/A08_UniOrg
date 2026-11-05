<?php

namespace App\Http\Middleware;

use App\Services\AuthenticationActivity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RecordFailedLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! Auth::check()
            && is_string($request->input('student_id'))
            && $request->filled('student_id')
            && $request->filled('password')) {
            app(AuthenticationActivity::class)->record('login.failed', $request);
        }

        return $response;
    }
}
