<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isPostgres = DB::connection()->getDriverName() === 'pgsql';

        // 1. Drop existing check constraint (PostgreSQL only; SQLite cannot
        //    alter table constraints in place).
        if ($isPostgres) {
            DB::statement("ALTER TABLE system_requests DROP CONSTRAINT IF EXISTS system_requests_organization_type_check");
        }

        // 2. Map old values to new values so the constraint passes
        DB::table('system_requests')->where('organization_type', 'Academic')->update(['organization_type' => 'Mother Org']);

        if (! $isPostgres) {
            return;
        }

        // 3. Ensure column is VARCHAR first
        DB::statement("ALTER TABLE system_requests ALTER COLUMN organization_type TYPE VARCHAR(255)");

        // 4. Add the new check constraint with your specific values
        DB::statement("ALTER TABLE system_requests ADD CONSTRAINT system_requests_organization_type_check CHECK (organization_type IN ('Mother Org', 'Sub Org', 'Non-Academic'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE system_requests DROP CONSTRAINT IF EXISTS system_requests_organization_type_check");
        DB::statement("ALTER TABLE system_requests ALTER COLUMN organization_type TYPE VARCHAR(255)");
        DB::statement("ALTER TABLE system_requests ADD CONSTRAINT system_requests_organization_type_check CHECK (organization_type IN ('Academic', 'Non-Academic'))");
    }
};
