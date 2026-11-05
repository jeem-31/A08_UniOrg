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
        // Check-constraint rewrites are PostgreSQL-only; SQLite keeps the
        // column definition and relies on application validation.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // For PostgreSQL, drop the old enum check constraint on the requester_position column
        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS system_requests_requester_position_check");
        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS apply_orgs_requester_position_check");
        
        // Alter column to varchar(255) to support custom sizes
        DB::statement("ALTER TABLE apply_orgs ALTER COLUMN requester_position TYPE VARCHAR(255)");
        
        // Add new check constraint allowing all four valid roles
        DB::statement("ALTER TABLE apply_orgs ADD CONSTRAINT apply_orgs_requester_position_check CHECK (requester_position IN ('Organization President', 'Organization Adviser', 'Organization Senior Adviser', 'Organization Junior Adviser'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE apply_orgs DROP CONSTRAINT IF EXISTS apply_orgs_requester_position_check");
        
        DB::statement("ALTER TABLE apply_orgs ADD CONSTRAINT system_requests_requester_position_check CHECK (requester_position IN ('Organization President', 'Organization Adviser'))");
    }
};
