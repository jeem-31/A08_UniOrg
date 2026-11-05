<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add indexes for organization_id (most important for multi-tenant)
        DB::statement('CREATE INDEX IF NOT EXISTS announcements_organization_id_idx ON announcements(organization_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS events_organization_id_idx ON events(organization_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS payments_organization_id_idx ON payments(organization_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS library_resources_organization_id_idx ON library_resources(organization_id)');
        
        // Add composite index for published content queries
        DB::statement('CREATE INDEX IF NOT EXISTS announcements_org_published_created_idx ON announcements(organization_id, is_published, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS events_org_published_created_idx ON events(organization_id, is_published, created_at)');
        
        // Add index for user lookups
        DB::statement('CREATE INDEX IF NOT EXISTS users_email_idx ON users(email)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_role_idx ON users(role)');
        
        // Add index for student payments
        DB::statement('CREATE INDEX IF NOT EXISTS student_payments_user_status_idx ON student_payments(user_id, status)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS announcements_organization_id_idx');
        DB::statement('DROP INDEX IF EXISTS events_organization_id_idx');
        DB::statement('DROP INDEX IF EXISTS payments_organization_id_idx');
        DB::statement('DROP INDEX IF EXISTS library_resources_organization_id_idx');
        DB::statement('DROP INDEX IF EXISTS announcements_org_published_created_idx');
        DB::statement('DROP INDEX IF EXISTS events_org_published_created_idx');
        DB::statement('DROP INDEX IF EXISTS users_email_idx');
        DB::statement('DROP INDEX IF EXISTS users_role_idx');
        DB::statement('DROP INDEX IF EXISTS student_payments_user_status_idx');
    }
};
