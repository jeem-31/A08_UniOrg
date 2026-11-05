<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOrganizationAccess
{
    /**
     * Handle an incoming request.
     * 
     * Rules:
     * - Mother Org: Dean, Advisers, Officers, Students all have access
     * - Sub-Org: Only Advisers and Officers have access (no student pages)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            abort(403, 'You must be logged in to access this page.');
        }

        // Check if current_organization_id is set in session
        $currentOrgId = session('current_organization_id');
        
        if (!$currentOrgId) {
            abort(403, 'No organization selected. Please select an organization to continue.');
        }

        $user = auth()->user();

        // Get user's membership in the current organization
        $membership = $user->organizations()
            ->where('organizations.id', $currentOrgId)
            ->first();

        if (!$membership) {
            abort(403, 'You are not a member of this organization.');
        }

        $userRole = $membership->pivot->role;

        // Get organization
        $organization = \App\Models\Organization::find($currentOrgId);

        if (!$organization) {
            abort(403, 'Organization not found.');
        }

        // If Sub-Org, deny access to students
        if ($organization->isSubOrg() && $userRole === 'Student') {
            abort(403, 'Students cannot access Sub-Organization systems directly. Sub-Org content is visible through your Mother Organization.');
        }

        // Dean, Advisers, and Officers can access any organization type
        // Students can access Mother Org
        return $next($request);
    }
}
