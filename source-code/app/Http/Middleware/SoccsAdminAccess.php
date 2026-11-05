<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SoccsAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isSoccsAdmin()) {
            abort(403, 'SOCCS Admin access only.');
        }

        return $next($request);
    }
}
