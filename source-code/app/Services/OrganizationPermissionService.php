<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrganizationPermissionService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_PREFIX = 'org_permission:';
    
    public function __construct(
        private PermissionMatrix $matrix
    ) {}
    
    /**
     * Check if user has required access to module.
     *
     * Access is granted if EITHER:
     *   (a) The PermissionMatrix grants full/read access for the position, OR
     *   (b) An additive grant exists in organization_role_permissions for the role+org
     *
     * @param int $userId User ID
     * @param int $organizationId Organization ID
     * @param string $module Module name (announcements, events, documents, etc.)
     * @param string $requiredLevel Required access level (full, read)
     * @return bool True if user has access, false otherwise
     */
    public function hasAccess(int $userId, int $organizationId, string $module, string $requiredLevel): bool
    {
        $startTime = microtime(true);
        
        // First check if the module is enabled in organization settings
        if (!$this->isModuleEnabled($organizationId, $module)) {
            $this->logAuthorizationCheck($userId, $organizationId, $module, $requiredLevel, false, $startTime, 'module_disabled');
            return false;
        }
        
        $position = $this->getPosition($userId, $organizationId);
        
        if (!$position) {
            $this->logAuthorizationCheck($userId, $organizationId, $module, $requiredLevel, false, $startTime, 'no_position');
            return false;
        }

        // COMELEC is elections-only — block any other module even if a grant row exists
        $canonicalPosition = $this->matrix->canonicalize(\App\Models\OrganizationUser::formatDisplayLabel($position));
        if ($canonicalPosition === 'COMELEC' && $module !== 'elections') {
            $this->logAuthorizationCheck($userId, $organizationId, $module, $requiredLevel, false, $startTime, 'comelec_elections_only');
            return false;
        }
        
        // Elections are exclusively managed by the COMELEC position. Keep
        // this rule authoritative even when an older additive permission row
        // still exists for another organization role.
        if ($module === 'elections' && $requiredLevel === 'full' && $position !== 'COMELEC') {
            $this->logAuthorizationCheck($userId, $organizationId, $module, $requiredLevel, false, $startTime, 'comelec_only');
            return false;
        }

        // Positions that historically had election access keep read-only
        // visibility, including when their organization uses managed roles.
        if ($module === 'elections' && $requiredLevel === 'read'
            && in_array($position, [
                'President',
                'Organization President',
                'Vice President',
                'Adviser',
                'Organization Adviser',
                'Senior Adviser',
                'Junior Adviser',
            ], true)) {
            return $this->isModuleEnabled($organizationId, $module);
        }

        // Managed positions have an explicit profile created by the admin.
        // Legacy positions continue to use the static PermissionMatrix.
        $accessLevel = $this->getPositionAccessLevel($organizationId, $position, $module);
        $hasAccess = $this->meetsRequirement($accessLevel, $requiredLevel);
        
        // If not granted by default matrix, check for additive role grants
        if (!$hasAccess && $this->hasGrantedAccess($organizationId, $position, $module)) {
            $hasAccess = true;
            $this->logAuthorizationCheck($userId, $organizationId, $module, $requiredLevel, true, $startTime, 'granted_via_role_override');
            return true;
        }
        
        $this->logAuthorizationCheck($userId, $organizationId, $module, $requiredLevel, $hasAccess, $startTime, $hasAccess ? 'granted' : 'denied');
        
        return $hasAccess;
    }

    /**
     * Check if a role has been explicitly granted access to a module for an organization.
     * This is the additive layer on top of PermissionMatrix defaults.
     *
     * @param int $organizationId Organization ID
     * @param string $role Role/position string (e.g. 'Secretary')
     * @param string $module Module name
     * @return bool True if an active grant exists
     */
    public function hasGrantedAccess(int $organizationId, string $role, string $module): bool
    {
        // COMELEC lock: elections-only — never consider grants for other modules
        $canonicalForCheck = $this->matrix->canonicalize(\App\Models\OrganizationUser::formatDisplayLabel($role));
        if ($canonicalForCheck === 'COMELEC' && $module !== 'elections') {
            return false;
        }

        $cacheKey = self::CACHE_PREFIX . "grant:{$organizationId}:{$role}:{$module}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($organizationId, $role, $module) {
            // Direct match first (fast path for most roles)
            $exists = DB::table('organization_role_permissions')
                ->where('organization_id', $organizationId)
                ->where('role', $role)
                ->where('module', $module)
                ->whereRaw('is_granted = true')
                ->exists();

            if ($exists) {
                return true;
            }

            // Abbreviation-tolerant fallback: "1st Yr Rep" in DB should also be found
            // when lookup uses "1st Year Representative" and vice-versa.
            $canonical = $this->matrix->canonicalize(\App\Models\OrganizationUser::formatDisplayLabel($role));
            if ($canonical !== $role) {
                return DB::table('organization_role_permissions')
                    ->where('organization_id', $organizationId)
                    ->where('role', $canonical)
                    ->where('module', $module)
                    ->whereRaw('is_granted = true')
                    ->exists();
            }

            return false;
        });
    }

    /**
     * Invalidate grant caches for all modules for a given role+org combination.
     *
     * @param int $organizationId Organization ID
     * @param string $role Role string
     * @return void
     */
    public function invalidateGrantCache(int $organizationId, string $role): void
    {
        $modules = ['announcements', 'events', 'payments', 'chatbot', 'elections', 'library', 'edushare', 'documents', 'complaints'];
        foreach ($modules as $module) {
            Cache::forget(self::CACHE_PREFIX . "grant:{$organizationId}:{$role}:{$module}");
        }
        Cache::forget(self::CACHE_PREFIX . "managed_position:{$organizationId}:{$role}");

        // Bump the org-wide grants version. This is what the sidebar's nav cache key
        // is stamped with, so old session-cached nav permissions become stale
        // automatically — no need to reach into the sessions table (which doesn't
        // work reliably across session drivers, e.g. 'file').
        $this->bumpGrantsVersion($organizationId);
    }

    /**
     * Get the current "grants version" for an organization. This number changes
     * every time a role permission grant is added/removed, and is used as part
     * of the sidebar navigation cache key so stale session caches are bypassed.
     *
     * @param int $organizationId Organization ID
     * @return int Current version number
     */
    public function getGrantsVersion(int $organizationId): int
    {
        return (int) Cache::get(self::CACHE_PREFIX . "grants_version:{$organizationId}", 1);
    }

    /**
     * Increment the "grants version" for an organization, invalidating any
     * cached data (including session-based nav caches) keyed by the old version.
     *
     * @param int $organizationId Organization ID
     * @return void
     */
    public function bumpGrantsVersion(int $organizationId): void
    {
        $key = self::CACHE_PREFIX . "grants_version:{$organizationId}";
        $current = (int) Cache::get($key, 1);
        Cache::forever($key, $current + 1);
    }
    
    /**
     * Get user's position in organization (with caching)
     *
     * @param int $userId User ID
     * @param int $organizationId Organization ID
     * @return string|null Position/role in organization or null if not found
     */
    public function getPosition(int $userId, int $organizationId): ?string
    {
        $cacheKey = self::CACHE_PREFIX . "{$userId}:{$organizationId}";
        $cacheHit = Cache::has($cacheKey);
        
        $position = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId, $organizationId) {
            return DB::table('organization_user')
                ->where('user_id', $userId)
                ->where('organization_id', $organizationId)
                ->where('membership_status', 'Active')
                ->value('role');
        });
        
        // Log cache hit/miss
        Log::channel('performance')->info('Permission cache', [
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'cache_hit' => $cacheHit,
            'position' => $position,
        ]);
        
        return $position;
    }

    /**
     * Resolve access for a position in an organization.
     *
     * A row in organization_positions marks the role as admin-managed. Its
     * permission rows are authoritative, including explicit false values.
     * Roles without a definition retain the legacy matrix + additive grant
     * behavior.
     */
    public function getPositionAccessLevel(int $organizationId, string $position, string $module): string
    {
        if ($this->isManagedPosition($organizationId, $position)) {
            return $this->hasGrantedAccess($organizationId, $position, $module) ? 'full' : 'none';
        }

        return $this->getAccessLevel($position, $module);
    }

    public function isManagedPosition(int $organizationId, string $position): bool
    {
        return Cache::remember(
            self::CACHE_PREFIX . "managed_position:{$organizationId}:{$position}",
            self::CACHE_TTL,
            fn () => DB::table('organization_positions')
                ->where('organization_id', $organizationId)
                ->where('name', $position)
                ->exists(),
        );
    }
    
    /**
     * Get access level for position-module combination
     *
     * @param string $position The organizational position (President, Vice_president, etc.)
     * @param string $module The module name (announcements, events, documents, etc.)
     * @return string Access level: 'full', 'read', or 'none'
     */
    public function getAccessLevel(string $position, string $module): string
    {
        return $this->matrix->getAccessLevel($position, $module);
    }
    
    /**
     * Check if access level meets requirement
     *
     * @param string $accessLevel The user's access level (full, read, none)
     * @param string $requiredLevel The required access level (full, read)
     * @return bool True if access level meets requirement
     */
    private function meetsRequirement(string $accessLevel, string $requiredLevel): bool
    {
        if ($accessLevel === 'none') {
            return false;
        }
        
        if ($requiredLevel === 'read') {
            return in_array($accessLevel, ['read', 'full']);
        }
        
        if ($requiredLevel === 'full') {
            return $accessLevel === 'full';
        }
        
        return false;
    }
    
    /**
     * Invalidate cached permissions for user
     *
     * @param int $userId User ID
     * @param int|null $organizationId Optional organization ID to clear specific cache
     * @return void
     */
    public function invalidateCache(int $userId, ?int $organizationId = null): void
    {
        $modules = ['announcements', 'events', 'payments', 'chatbot', 'elections', 'library', 'edushare', 'documents', 'complaints'];

        if ($organizationId) {
            Cache::forget(self::CACHE_PREFIX . "{$userId}:{$organizationId}");
            
            foreach ($modules as $module) {
                Cache::forget(self::CACHE_PREFIX . "settings:{$organizationId}:{$module}");
            }
        } else {
            // Clear all organization permissions for user
            $organizations = DB::table('organization_user')
                ->where('user_id', $userId)
                ->pluck('organization_id');
            
            foreach ($organizations as $orgId) {
                Cache::forget(self::CACHE_PREFIX . "{$userId}:{$orgId}");
                
                foreach ($modules as $module) {
                    Cache::forget(self::CACHE_PREFIX . "settings:{$orgId}:{$module}");
                }
            }
        }
    }
    
    public function hasAnyModuleAccess(int $userId, int $organizationId): bool
    {
        return !empty($this->getAccessibleModules($userId, $organizationId));
    }

    /**
     * Get all accessible modules for user.
     * Includes both PermissionMatrix defaults AND additive role grants.
     *
     * @param int $userId User ID
     * @param int $organizationId Organization ID
     * @return array Associative array of module => access_level
     */
    public function getAccessibleModules(int $userId, int $organizationId): array
    {
        $position = $this->getPosition($userId, $organizationId);
        
        if (!$position) {
            return [];
        }
        
        // Managed positions are controlled entirely by the organization's
        // explicit permission profile. Legacy positions start with matrix
        // defaults and can receive additive grants.
        $allModules = $this->isManagedPosition($organizationId, $position)
            ? []
            : $this->matrix->getModulesForPosition($position);
        
        // Add any modules granted via role overrides (not already in matrix results)
        foreach ($this->matrix->getAllModules() as $module) {
            if (!isset($allModules[$module]) && $this->hasGrantedAccess($organizationId, $position, $module)) {
                $allModules[$module] = 'full';
            }
        }

        // Filter out modules that are not enabled in organization settings
        $enabledModules = [];
        foreach ($allModules as $module => $accessLevel) {
            if ($this->isModuleEnabled($organizationId, $module)) {
                $enabledModules[$module] = $accessLevel;
            }
        }
        
        return $enabledModules;
    }
    
    /**
     * Check if module is enabled in organization settings
     *
     * @param int $organizationId Organization ID
     * @param string $module Module name
     * @return bool True if module is enabled
     */
    private function isModuleEnabled(int $organizationId, string $module): bool
    {
        // Map module names to organization_settings columns
        $moduleColumnMap = [
            'announcements' => 'has_announcements',
            'events' => 'has_events',
            'payments' => 'has_payments',
            'chatbot' => 'has_chatbot',
            'elections' => 'has_elections',
            'library' => 'has_elibrary',
            'edushare' => 'has_edushare',
            'documents' => 'has_documents',
            'complaints' => 'has_complaints',
        ];
        
        $columnName = $moduleColumnMap[$module] ?? null;
        
        if (!$columnName) {
            return false;
        }
        
        // Cache the organization settings check
        $cacheKey = self::CACHE_PREFIX . "settings:{$organizationId}:{$module}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($organizationId, $columnName) {
            $result = DB::table('organization_settings')
                ->where('organization_id', $organizationId)
                ->value($columnName);

            // If no organization_settings record exists yet, default to enabled
            if ($result === null) {
                return true;
            }

            // PostgreSQL returns boolean as true/false, handle both
            return $result === true || $result === 'true' || $result === 't' || $result === 1;
        });
    }
    
    /**
     * Log authorization check with duration
     *
     * @param int $userId User ID
     * @param int $organizationId Organization ID
     * @param string $module Module name
     * @param string $requiredLevel Required access level
     * @param bool $granted Whether access was granted
     * @param float $startTime Start time in microseconds
     * @param string $reason Reason for the decision
     * @return void
     */
    private function logAuthorizationCheck(
        int $userId,
        int $organizationId,
        string $module,
        string $requiredLevel,
        bool $granted,
        float $startTime,
        string $reason = 'unknown'
    ): void {
        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        
        try {
            Log::channel('performance')->info('Authorization check', [
                'user_id' => $userId,
                'organization_id' => $organizationId,
                'module' => $module,
                'required_level' => $requiredLevel,
                'granted' => $granted,
                'reason' => $reason,
                'duration_ms' => round($duration, 2),
            ]);
        } catch (\Throwable) {
            // Silently ignore logging failures in performance channel
        }
    }
}
