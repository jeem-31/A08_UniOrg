<?php

use App\Services\AccountManagement\OrganizationPositionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove the " 2" officer role suffixes (e.g. "Business Manager 2").
 *
 * Two holders of the same position are now stored with the same canonical role
 * ("Business Manager"), so the uniqueness backstop that required a distinct
 * "Business Manager 2" role must be dropped and existing rows converted.
 *
 *   - Drops the partial unique index on (organization_id, role) for active
 *     officer/adviser roles, which would reject two identical active roles.
 *   - Re-rolers "Business Manager 2", "Public Information Officer 2", and
 *     "Event Coordinator 2" to their base roles.
 *   - Recreates a non-unique partial index on (organization_id, role) so the
 *     canonical-slot queries keep their index while duplicates are allowed.
 */
return new class extends Migration
{
    private const TWO_SUFFIX_ROLES = [
        'Business Manager 2' => 'Business Manager',
        'Public Information Officer 2' => 'Public Information Officer',
        'Event Coordinator 2' => 'Event Coordinator',
    ];

    public function up(): void
    {
        $this->requirePartialIndexSupport();

        DB::transaction(function (): void {
            $affectedAssignments = DB::table('organization_user')
                ->whereIn('role', array_keys(self::TWO_SUFFIX_ROLES))
                ->select('user_id', 'organization_id')
                ->get();

            DB::statement('DROP INDEX IF EXISTS organization_user_active_canonical_slot_unique');

            foreach (self::TWO_SUFFIX_ROLES as $twoSuffixRole => $canonicalRole) {
                DB::table('organization_user')
                    ->where('role', $twoSuffixRole)
                    ->update(['role' => $canonicalRole, 'updated_at' => now()]);
            }

            $this->createActiveRoleIndex(
                'organization_user_active_canonical_slot_index',
                'organization_id, role',
            );

            $permissionService = app(\App\Services\OrganizationPermissionService::class);
            foreach ($affectedAssignments as $assignment) {
                $permissionService->invalidateCache((int) $assignment->user_id, (int) $assignment->organization_id);
            }
        });
    }

    public function down(): void
    {
        $this->requirePartialIndexSupport();

        DB::transaction(function (): void {
            DB::statement('DROP INDEX IF EXISTS organization_user_active_canonical_slot_index');

            // Restoring the unique canonical-slot backstop is only safe when no
            // duplicate active roles exist; otherwise partial-index creation fails.
            $duplicateActiveCanonicalRoles = DB::table('organization_user')
                ->selectRaw('organization_id, role, COUNT(*) as holders')
                ->where('membership_status', 'Active')
                ->whereIn('role', $this->activeRoles())
                ->groupBy('organization_id', 'role')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if (! $duplicateActiveCanonicalRoles) {
                $this->createActiveRoleIndex(
                    'organization_user_active_canonical_slot_unique',
                    'organization_id, role',
                );
            }
        });
    }

    /** @return list<string> */
    private function activeRoles(): array
    {
        return [
            ...OrganizationPositionRegistry::officerRoles(),
            ...OrganizationPositionRegistry::adviserRoles(),
        ];
    }

    private function createActiveRoleIndex(string $name, string $columns): void
    {
        $quotedRoles = implode(', ', array_map(
            static fn (string $role): string => "'{$role}'",
            $this->activeRoles(),
        ));

        DB::statement(
            "CREATE INDEX {$name}\n"
            ."ON organization_user ({$columns})\n"
            ."WHERE membership_status = 'Active' AND role IN ({$quotedRoles})"
        );
    }

    private function requirePartialIndexSupport(): void
    {
        if (! in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            throw new \RuntimeException(
                'Canonical-slot indexes require PostgreSQL or SQLite partial indexes.',
            );
        }
    }
};
