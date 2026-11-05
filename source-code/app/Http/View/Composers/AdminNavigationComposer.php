<?php

namespace App\Http\View\Composers;

use App\Services\PermissionHelper;
use Illuminate\View\View;

class AdminNavigationComposer
{
    public function __construct(
        private PermissionHelper $permissionHelper,
        private \App\Services\OrganizationPermissionService $permissionService
    ) {}

    /**
     * Bind data to the view.
     * 
     * OPTIMIZATION: Cache all navigation permissions in session
     * This prevents repeated permission checks on every page load
     */
    public function compose(View $view): void
    {
        $user = auth()->user();
        
        if (!$user) {
            $view->with('navPermissions', []);
            return;
        }
        
        // OPTIMIZATION: Use session cache for navigation permissions
        // Cache key includes user ID, organization ID, AND the org's current
        // "grants version" — so when an admin grants/revokes a module in
        // Manage Admins, the version bumps and old session caches are
        // automatically bypassed regardless of session driver (file, db, etc.)
        $organizationId = session('current_organization_id') ?? $user->organizations()->first()?->id;
        $grantsVersion = $organizationId ? $this->permissionService->getGrantsVersion($organizationId) : 1;
        $cacheKey = "nav_permissions_{$user->id}_{$organizationId}_v{$grantsVersion}";
        
        // Check if permissions are already cached in session
        $navPermissions = session($cacheKey);
        
        // Also check if we need to invalidate cache due to organization change
        $lastOrgId = session('last_nav_org_id');
        if ($lastOrgId !== $organizationId) {
            // Organization changed, clear all cached permissions
            $navPermissions = null;
            session(['last_nav_org_id' => $organizationId]);
        }
        
        if ($navPermissions === null) {
            // First time or cache expired - fetch all permissions at once
            $modules = [
                'announcements',
                'events', 
                'payments',
                'elections',
                'library',
                'edushare',
                'documents',
                'chatbot',
                'complaints'
            ];
            
            $navPermissions = [
                'modules' => [],
                'readOnly' => []
            ];
            
            foreach ($modules as $module) {
                $canAccess = $this->permissionHelper->canAccessModule($module);
                $navPermissions['modules'][$module] = $canAccess;
                
                if ($canAccess) {
                    $navPermissions['readOnly'][$module] = $this->permissionHelper->hasReadOnly($module);
                }
            }
            
            // Cache in session for the entire session duration
            // This means permissions are checked ONCE per login, not per page load
            session([$cacheKey => $navPermissions]);
        }

        // Elections are now restricted to COMELEC. Recalculate this one
        // sensitive item on every render so sessions created before the rule
        // changed cannot keep showing an obsolete Elections tab.
        $navPermissions['modules']['elections'] = $this->permissionHelper->canAccessModule('elections');
        $navPermissions['readOnly']['elections'] = $navPermissions['modules']['elections']
            && $this->permissionHelper->hasReadOnly('elections');
        if (!$navPermissions['modules']['elections']) {
            unset($navPermissions['readOnly']['elections']);
        }

        $view->with('navPermissions', $navPermissions);
    }
}
