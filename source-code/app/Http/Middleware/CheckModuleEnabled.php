<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $module  The module name to check (e.g., 'elections', 'elibrary', 'edushare')
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        // Check if the module is enabled for the current organization
        if (!module_enabled($module)) {
            abort(403, "The {$module} module is not enabled for your organization. Please contact your organization administrator to enable this feature.");
        }

        return $next($request);
    }
}
