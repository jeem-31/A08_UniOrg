<?php

namespace App\Http\Middleware;

use App\Services\OrganizationPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the system-level admin account used by COMELEC to election pages.
 * Other admin accounts continue through the normal admin authorization flow.
 */
class ComelecOnlyElection
{
    public function __construct(private OrganizationPermissionService $permissions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organizationId = session('current_organization_id')
            ?: $user?->organizations()->first()?->id;

        if ($user && $organizationId && $this->permissions->getPosition($user->id, $organizationId) === 'COMELEC'
            && !str_starts_with((string) $request->route()?->getName(), 'admin.elections.')
            && !str_starts_with((string) $request->route()?->getName(), 'admin.candidates.')
            && !str_starts_with((string) $request->route()?->getName(), 'admin.profile.')
            && !str_starts_with((string) $request->route()?->getName(), 'admin.members.')) {
            abort(403, 'COMELEC accounts may only access the Elections module.');
        }

        return $next($request);
    }
}
