<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove the "Assistant Secretary" officer position from the system.
 *
 * The position was removed from the registries and application form, so any
 * existing records that still carry the role must be cleaned up too:
 *   - Active/inactive org memberships with role "Assistant Secretary" are
 *     deleted (they cannot be renamed to "Secretary" because a unique partial
 *     index enforces a single canonical slot per role per organization).
 *   - Pending application officers with temp_position "Assistant Secretary"
 *     are re-rolered to "Secretary" so they remain officers on approval.
 *   - Org-scoped position/grants rows are removed as well.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $affectedAssignments = DB::table('organization_user')
                ->where('role', 'Assistant Secretary')
                ->select('user_id', 'organization_id')
                ->get();

            DB::table('organization_user')
                ->where('role', 'Assistant Secretary')
                ->delete();

            DB::table('users')
                ->where('temp_position', 'Assistant Secretary')
                ->update(['temp_position' => 'Secretary', 'updated_at' => now()]);

            DB::table('organization_role_permissions')
                ->where('role', 'Assistant Secretary')
                ->delete();

            DB::table('organization_positions')
                ->where('name', 'Assistant Secretary')
                ->delete();

            $permissionService = app(\App\Services\OrganizationPermissionService::class);
            foreach ($affectedAssignments as $assignment) {
                $permissionService->invalidateCache((int) $assignment->user_id, (int) $assignment->organization_id);
            }
        });
    }

    public function down(): void
    {
        // Destructive removal of memberships cannot be restored from here.
    }
};
