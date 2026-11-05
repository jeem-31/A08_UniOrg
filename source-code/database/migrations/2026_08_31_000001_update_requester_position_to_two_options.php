<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Limit requester_position to 2 options: Organization Adviser + Organization President
     * Backfill legacy Senior/Junior Adviser rows to generic Adviser.
     */
    public function up(): void
    {
        // Backfill legacy data first (works on all drivers) before tightening constraint
        DB::table('apply_orgs')
            ->whereIn('requester_position', ['Organization Senior Adviser', 'Organization Junior Adviser'])
            ->update(['requester_position' => 'Organization Adviser']);

        // Also backfill archives if table exists
        try {
            if (DB::connection()->getDriverName() !== 'pgsql') {
                return;
            }
            // Check if archives table exists
            $hasArchives = DB::table('information_schema.tables')
                ->where('table_name', 'apply_org_document_archives')
                ->exists();
            if ($hasArchives) {
                DB::table('apply_org_document_archives')
                    ->whereIn('requester_position', ['Organization Senior Adviser', 'Organization Junior Adviser'])
                    ->update(['requester_position' => 'Organization Adviser']);
            }
        } catch (\Throwable $e) {
            // ignore backfill errors for archives
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS system_requests_requester_position_check");
        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS apply_orgs_requester_position_check");

        DB::statement("ALTER TABLE apply_orgs ADD CONSTRAINT apply_orgs_requester_position_check CHECK (requester_position IN ('Organization President', 'Organization Adviser'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            // Restore legacy data distinction is not possible (generic -> Senior is lossy)
            return;
        }

        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS apply_orgs_requester_position_check");
        DB::statement("ALTER TABLE apply_orgs ADD CONSTRAINT apply_orgs_requester_position_check CHECK (requester_position IN ('Organization President', 'Organization Adviser', 'Organization Senior Adviser', 'Organization Junior Adviser'))");
    }
};
