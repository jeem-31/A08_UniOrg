<?php

namespace App\Services;

class PermissionHelper
{
    // OPTIMIZATION: Request-level cache to avoid repeated permission checks
    private array $permissionCache = [];
    private ?int $cachedOrgId = null;
    
    public function __construct(
        private OrganizationPermissionService $permissionService
    ) {}
    
    /**
     * Check if user can access module (any access level)
     *
     * @param string $module Module name (announcements, events, documents, etc.)
     * @return bool True if user has any access (read or full)
     */
    public function canAccessModule(string $module): bool
    {
        // OPTIMIZATION: Check cache first
        $cacheKey = "access_{$module}";
        if (isset($this->permissionCache[$cacheKey])) {
            return $this->permissionCache[$cacheKey];
        }
        
        $user = auth()->user();
        
        if (!$user) {
            return $this->permissionCache[$cacheKey] = false;
        }
        
        // Super admins and OSAS admins bypass position checks
        if (in_array($user->role, ['super_admin', 'osas_admin'])) {
            return $this->permissionCache[$cacheKey] = true;
        }
        
        // Get current organization context from session
        $organizationId = $this->getCurrentOrganizationId();
        
        if (!$organizationId) {
            return $this->permissionCache[$cacheKey] = false;
        }
        
        $result = $this->permissionService->hasAccess($user->id, $organizationId, $module, 'read');
        return $this->permissionCache[$cacheKey] = $result;
    }
    
    /**
     * Check if user has full access to module
     *
     * @param string $module Module name (announcements, events, documents, etc.)
     * @return bool True if user has full access
     */
    public function hasFullAccess(string $module): bool
    {
        // OPTIMIZATION: Check cache first
        $cacheKey = "full_{$module}";
        if (isset($this->permissionCache[$cacheKey])) {
            return $this->permissionCache[$cacheKey];
        }
        
        $user = auth()->user();
        
        if (!$user) {
            return $this->permissionCache[$cacheKey] = false;
        }
        
        // Super admins and OSAS admins have full access to all modules
        if (in_array($user->role, ['super_admin', 'osas_admin'])) {
            return $this->permissionCache[$cacheKey] = true;
        }
        
        // Get current organization context from session
        $organizationId = $this->getCurrentOrganizationId();
        
        if (!$organizationId) {
            return $this->permissionCache[$cacheKey] = false;
        }
        
        $result = $this->permissionService->hasAccess($user->id, $organizationId, $module, 'full');
        return $this->permissionCache[$cacheKey] = $result;
    }
    
    /**
     * Check if user has read-only access to module (not full access)
     *
     * @param string $module Module name (announcements, events, documents, etc.)
     * @return bool True if user has read-only access (not full)
     */
    public function hasReadOnly(string $module): bool
    {
        // OPTIMIZATION: Check cache first
        $cacheKey = "readonly_{$module}";
        if (isset($this->permissionCache[$cacheKey])) {
            return $this->permissionCache[$cacheKey];
        }
        
        $user = auth()->user();
        
        // Super admins and OSAS admins have full access, not read-only
        if (!$user || in_array($user->role, ['super_admin', 'osas_admin'])) {
            return $this->permissionCache[$cacheKey] = false;
        }
        
        // Get current organization context from session
        $organizationId = $this->getCurrentOrganizationId();
        
        if (!$organizationId) {
            return $this->permissionCache[$cacheKey] = false;
        }
        
        // Get user's position in the organization
        $position = $this->permissionService->getPosition($user->id, $organizationId);
        
        if (!$position) {
            return $this->permissionCache[$cacheKey] = false;
        }
        
        // Get the access level for this position-module combination
        $accessLevel = $this->permissionService->getAccessLevel($position, $module);

        // Legacy election positions retain view access, but only COMELEC may
        // manage elections. Reflect that distinction in the navigation.
        if ($module === 'elections' && strtoupper($position) !== 'COMELEC'
            && $this->permissionService->hasAccess($user->id, $organizationId, $module, 'read')) {
            return $this->permissionCache[$cacheKey] = true;
        }
        
        $result = $accessLevel === 'read';
        return $this->permissionCache[$cacheKey] = $result;
    }
    
    /**
     * Get all accessible modules for current user
     *
     * @return array Associative array of module => access_level
     */
    public function getAccessibleModules(): array
    {
        // OPTIMIZATION: Check cache first
        $cacheKey = 'all_modules';
        if (isset($this->permissionCache[$cacheKey])) {
            return $this->permissionCache[$cacheKey];
        }
        
        $user = auth()->user();
        
        if (!$user) {
            return $this->permissionCache[$cacheKey] = [];
        }
        
        // Super admins and OSAS admins have full access to all modules
        if (in_array($user->role, ['super_admin', 'osas_admin'])) {
            return $this->permissionCache[$cacheKey] = [
                'announcements' => 'full',
                'events' => 'full',
                'documents' => 'full',
                'complaints' => 'full',
                'payments' => 'full',
                'elections' => 'full',
            ];
        }
        
        // Get current organization context from session
        $organizationId = $this->getCurrentOrganizationId();
        
        if (!$organizationId) {
            return $this->permissionCache[$cacheKey] = [];
        }
        
        $result = $this->permissionService->getAccessibleModules($user->id, $organizationId);
        return $this->permissionCache[$cacheKey] = $result;
    }
    
    /**
     * Get current organization ID from session
     *
     * @return int|null Organization ID or null if not found
     */
    private function getCurrentOrganizationId(): ?int
    {
        // OPTIMIZATION: Cache organization ID for this request
        if ($this->cachedOrgId !== null) {
            return $this->cachedOrgId;
        }
        
        // Get from session first
        $organizationId = session('current_organization_id');
        
        if ($organizationId) {
            return $this->cachedOrgId = $organizationId;
        }
        
        // Fall back to user's first organization
        $user = auth()->user();
        if ($user) {
            $firstOrganization = $user->organizations()->first();
            return $this->cachedOrgId = $firstOrganization?->id;
        }
        
        return null;
    }
}
