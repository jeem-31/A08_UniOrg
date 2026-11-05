<?php

use App\Services\AccountManagement\OrganizationPositionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->requirePartialIndexSupport();

        DB::transaction(function (): void {
            $this->remediateDuplicateActiveDeans();

            DB::statement(
                "CREATE UNIQUE INDEX users_one_active_super_admin_unique\n"
                ."ON users (role)\n"
                ."WHERE role = 'super_admin' AND is_active = true"
            );

            $this->createAppointmentIndexes();
        });
    }

    public function down(): void
    {
        $this->requirePartialIndexSupport();

        DB::transaction(function (): void {
            foreach ([
                'organization_user_active_adviser_user_unique',
                'organization_user_active_officer_user_unique',
                'organization_user_active_canonical_slot_unique',
                'users_one_active_super_admin_unique',
            ] as $indexName) {
                DB::statement("DROP INDEX IF EXISTS {$indexName}");
            }
        });
    }

    private function remediateDuplicateActiveDeans(): void
    {
        $activeDeanIds = DB::table('users')
            ->where('role', 'super_admin')
            ->where('is_active', DB::raw('true'))
            ->orderBy('id')
            ->pluck('id');

        if ($activeDeanIds->count() < 2) {
            return;
        }

        DB::table('users')
            ->whereIn('id', $activeDeanIds->slice(1)->all())
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    private function createAppointmentIndexes(): void
    {
        $adviserRoles = OrganizationPositionRegistry::adviserRoles();
        $officerRoles = OrganizationPositionRegistry::officerRoles();

        $this->createActiveRoleIndex(
            'organization_user_active_canonical_slot_unique',
            'organization_id, role',
            [...$adviserRoles, ...$officerRoles],
        );
        $this->createActiveRoleIndex(
            'organization_user_active_officer_user_unique',
            'organization_id, user_id',
            $officerRoles,
        );
        $this->createActiveRoleIndex(
            'organization_user_active_adviser_user_unique',
            'organization_id, user_id',
            $adviserRoles,
        );
    }

    /** @param list<string> $roles */
    private function createActiveRoleIndex(string $name, string $columns, array $roles): void
    {
        $quotedRoles = implode(', ', array_map(
            static fn (string $role): string => "'{$role}'",
            $roles,
        ));

        DB::statement(
            "CREATE UNIQUE INDEX {$name}\n"
            ."ON organization_user ({$columns})\n"
            ."WHERE membership_status = 'Active' AND role IN ({$quotedRoles})"
        );
    }

    private function requirePartialIndexSupport(): void
    {
        if (! in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            throw new \RuntimeException(
                'Active Dean and appointment uniqueness constraints require PostgreSQL or SQLite partial indexes.',
            );
        }
    }
};
