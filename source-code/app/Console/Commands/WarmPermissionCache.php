<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OrganizationPermissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WarmPermissionCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:warm-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-populate permission cache for active admin users';

    /**
     * Execute the console command.
     */
    public function handle(OrganizationPermissionService $permissionService)
    {
        $this->info('Starting permission cache warming...');

        // Get all active admin users
        $admins = User::where('role', 'admin')
            ->whereRaw('is_active = TRUE')
            ->get();

        if ($admins->isEmpty()) {
            $this->info('No active admin users found.');
            return;
        }

        $totalCached = 0;

        foreach ($admins as $admin) {
            // Get all active organizations for this admin
            $organizations = DB::table('organization_user')
                ->where('user_id', $admin->id)
                ->where('membership_status', 'Active')
                ->pluck('organization_id');

            foreach ($organizations as $orgId) {
                // Call getPosition() to populate cache
                $position = $permissionService->getPosition($admin->id, $orgId);
                
                if ($position) {
                    $totalCached++;
                    $this->line("Cached: User {$admin->id} ({$admin->full_name}) - Org {$orgId} - Position: {$position}");
                }
            }
        }

        $this->info("Permission cache warmed successfully!");
        $this->info("Total users processed: {$admins->count()}");
        $this->info("Total permissions cached: {$totalCached}");
    }
}
