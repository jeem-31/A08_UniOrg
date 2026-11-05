<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if we're using PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            // First, check if the enum type exists
            $enumExists = DB::select("
                SELECT EXISTS (
                    SELECT 1 FROM pg_type WHERE typname = 'user_role'
                )
            ")[0]->exists;

            if ($enumExists) {
                // Add new value to existing enum
                DB::statement("ALTER TYPE user_role ADD VALUE IF NOT EXISTS 'osas_admin'");
            } else {
                // If enum doesn't exist, alter the column directly
                DB::statement("
                    ALTER TABLE users 
                    DROP CONSTRAINT IF EXISTS users_role_check
                ");
                
                DB::statement("
                    ALTER TABLE users 
                    ADD CONSTRAINT users_role_check 
                    CHECK (role IN ('super_admin', 'admin', 'student', 'osas_admin'))
                ");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: PostgreSQL doesn't support removing enum values easily
        // This would require recreating the enum type
        // For now, we'll leave it as is
    }
};
