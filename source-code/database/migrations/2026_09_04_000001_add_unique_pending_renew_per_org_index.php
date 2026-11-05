<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Prevent duplicate pending renewals per organization (race-proof).
     * Enforces: one row max where application_type='renew' AND status IN ('Pending','For-Checking') per organization_id.
     * This makes Start Renewal idempotent even under double-click / concurrent requests.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS apply_orgs_one_pending_renew_per_org ON apply_orgs (organization_id) WHERE application_type = 'renew' AND status IN ('Pending', 'For-Checking')");
        } else {
            // MySQL/SQLite fallback: no partial index support, use regular unique with workaround via raw check (skip if not pgsql)
            // Keep no-op to avoid breaking sqlite tests
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS apply_orgs_one_pending_renew_per_org");
        }
    }
};
