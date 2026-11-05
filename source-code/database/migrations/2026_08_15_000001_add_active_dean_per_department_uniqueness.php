<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->requirePartialIndexSupport();

        DB::transaction(function (): void {
            DB::statement('DROP INDEX IF EXISTS users_one_active_super_admin_unique');

            // One active Dean per college. A NULL department is mapped to 0 so
            // that unassigned Dean accounts also compete for a single slot.
            DB::statement(
                "CREATE UNIQUE INDEX users_one_active_super_admin_per_department_unique\n"
                ."ON users (COALESCE(department_id, 0))\n"
                ."WHERE role = 'super_admin' AND is_active = true"
            );
        });
    }

    public function down(): void
    {
        $this->requirePartialIndexSupport();

        DB::transaction(function (): void {
            DB::statement('DROP INDEX IF EXISTS users_one_active_super_admin_per_department_unique');

            DB::statement(
                "CREATE UNIQUE INDEX users_one_active_super_admin_unique\n"
                ."ON users (role)\n"
                ."WHERE role = 'super_admin' AND is_active = true"
            );
        });
    }

    private function requirePartialIndexSupport(): void
    {
        if (! in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            throw new \RuntimeException(
                'Active Dean per-department uniqueness requires PostgreSQL or SQLite partial indexes.',
            );
        }
    }
};
