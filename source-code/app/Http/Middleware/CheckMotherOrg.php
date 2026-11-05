<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMotherOrg
{
    /**
     * Handle an incoming request.
     * 
     * This middleware ensures that only Mother Organizations can access certain routes.
     * Sub-Organizations will be denied access with a clear error message.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the current organization is a Mother Org
        if (!is_mother_org()) {
            abort(403, 'This feature is only available for Mother Organizations. Sub-Organization content is managed by Advisers and Officers, and is visible to Mother Organization students.');
        }

        return $next($request);
    }
}
