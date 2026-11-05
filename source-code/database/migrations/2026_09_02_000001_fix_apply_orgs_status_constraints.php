<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Hotfix for Render: ensure For-Checking is allowed.
     * Previous migration 2026_09_01_000002 may have been missed or left stale
     * system_requests_status_check constraint after rename system_requests -> apply_orgs.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Drop any stale constraints (both legacy and current names)
        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS system_requests_status_check");
        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS apply_orgs_status_check");

        // Ensure status can hold For-Checking (enum -> varchar)
        try {
            DB::statement("ALTER TABLE apply_orgs ALTER COLUMN status TYPE VARCHAR(255) USING status::text");
        } catch (\Throwable $e) {
            try {
                DB::statement("ALTER TABLE apply_orgs ALTER COLUMN status TYPE VARCHAR(255)");
            } catch (\Throwable $e2) {
                // already varchar
            }
        }

        // Create single canonical constraint
        DB::statement("ALTER TABLE apply_orgs ADD CONSTRAINT apply_orgs_status_check CHECK (status IN ('Pending', 'Approved', 'Rejected', 'For-Checking'))");

        // Defensive: ensure legacy name does not reappear
        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS system_requests_status_check");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Backfill For-Checking -> Pending for rollback
        DB::table('apply_orgs')->where('status', 'For-Checking')->update(['status' => 'Pending']);

        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS apply_orgs_status_check");
        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS system_requests_status_check");
        DB::statement("ALTER TABLE apply_orgs ADD CONSTRAINT apply_orgs_status_check CHECK (status IN ('Pending', 'Approved', 'Rejected'))");
        // Do NOT recreate legacy system_requests_status_check (keep single source of truth)
    }
};
