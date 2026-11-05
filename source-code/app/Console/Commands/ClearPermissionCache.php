<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OrganizationPermissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ClearPermissionCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:clear-cache {user_id? : The ID of the user to clear cache for (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear permission cache for a specific user or all users';

    /**
     * Execute the console command.
     */
    public function handle(OrganizationPermissionService $permissionService)
    {
        $userId = $this->argument('user_id');

        if ($userId) {
            // Clear cache for specific user
            $this->clearUserCache($userId, $permissionService);
        } else {
            // Clear all permission caches
            $this->clearAllCaches($permissionService);
        }
    }

    /**
     * Clear permission cache for a specific user
     *
     * @param int $userId
     * @param OrganizationPermissionService $permissionService
     * @return void
     */
    private function clearUserCache(int $userId, OrganizationPermissionService $permissionService): void
    {
        // Verify user exists
        $user = User::find($userId);

        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return;
        }

        // Get all organizations for this user
        $organizations = DB::table('organization_user')
            ->where('user_id', $userId)
            ->pluck('organization_id');

        if ($organizations->isEmpty()) {
            $userName = "{$user->first_name} {$user->last_name}";
            $this->warn("User {$userId} ({$userName}) has no organization memberships.");
            $this->info("No cache entries to clear.");
            return;
        }

        // Clear cache for each organization
        $permissionService->invalidateCache($userId);

        $userName = "{$user->first_name} {$user->last_name}";
        $this->info("Cleared permission cache for user {$userId} ({$userName})");
        $this->info("Organizations affected: {$organizations->count()}");
        
        // Display cleared organizations
        foreach ($organizations as $orgId) {
            $this->line("  - Organization ID: {$orgId}");
        }
    }

    /**
     * Clear all permission caches
     *
     * @param OrganizationPermissionService $permissionService
     * @return void
     */
    private function clearAllCaches(OrganizationPermissionService $permissionService): void
    {
        $this->info('Clearing all permission caches...');

        // Get all admin users with organization memberships
        $adminUsers = DB::table('organization_user')
            ->join('users', 'organization_user.user_id', '=', 'users.id')
            ->where('users.role', 'admin')
            ->select('users.id')
            ->distinct()
            ->get();

        if ($adminUsers->isEmpty()) {
            $this->info('No admin users with organization memberships found.');
            $this->info('No cache entries to clear.');
            return;
        }

        $clearedCount = 0;

        foreach ($adminUsers as $user) {
            $permissionService->invalidateCache($user->id);
            $clearedCount++;
        }

        $this->info("Successfully cleared permission caches!");
        $this->info("Total users affected: {$clearedCount}");
    }
}
