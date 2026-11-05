<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard();
        $authenticatedUser = $request->user();
        $user = $authenticatedUser
            ? User::query()->find($authenticatedUser->getAuthIdentifier())
            : null;

        if (! $user || ! $user->is_active) {
            $guard->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            throw new AuthenticationException(
                'Unauthenticated.',
                [config('auth.defaults.guard', 'web')],
                route('login'),
            );
        }

        $guard->setUser($user);

        return $next($request);
    }
}
