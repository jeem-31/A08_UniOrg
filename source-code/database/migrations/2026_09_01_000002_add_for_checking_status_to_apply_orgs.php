<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Drop both legacy and new constraint names (rename kept old name)
        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS system_requests_status_check");
        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS apply_orgs_status_check");

        // Ensure column is varchar (handles enum -> varchar conversion); use USING for enum cast
        try {
            DB::statement("ALTER TABLE apply_orgs ALTER COLUMN status TYPE VARCHAR(255) USING status::text");
        } catch (\Throwable $e) {
            // Fallback for already-varchar
            try {
                DB::statement("ALTER TABLE apply_orgs ALTER COLUMN status TYPE VARCHAR(255)");
            } catch (\Throwable $e2) {
                // ignore if already varchar
            }
        }

        DB::statement("ALTER TABLE apply_orgs ADD CONSTRAINT apply_orgs_status_check CHECK (status IN ('Pending', 'Approved', 'Rejected', 'For-Checking'))");
        // Ensure legacy name is also gone (do not recreate it) — defensive double-drop
        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS system_requests_status_check");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Backfill For-Checking -> Pending for rollback (lossy but safe)
        DB::table('apply_orgs')->where('status', 'For-Checking')->update(['status' => 'Pending']);

        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS apply_orgs_status_check");
        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS system_requests_status_check");
        DB::statement("ALTER TABLE apply_orgs ADD CONSTRAINT apply_orgs_status_check CHECK (status IN ('Pending', 'Approved', 'Rejected'))");
        // Do not recreate legacy system_requests_status_check — single source of truth
    }
};
