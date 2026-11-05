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
        // Add department_id column to organizations table
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('type')->constrained('departments')->onDelete('set null')->comment('Department reference (null for Non-Academic orgs)');
        });

        // The remaining steps only reorder physical columns, which is a
        // PostgreSQL-specific rebuild. Column order is irrelevant to behavior,
        // so portable dialects (SQLite tests) stop after adding the column.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Reorder columns using PostgreSQL (create new table with correct order, copy data, drop old, rename new)
        DB::statement('
            CREATE TABLE organizations_new (
                id BIGSERIAL PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                org_acronym VARCHAR(20),
                type VARCHAR(255) NOT NULL,
                department_id BIGINT,
                status VARCHAR(255) NOT NULL DEFAULT \'Pending\',
                has_system BOOLEAN NOT NULL DEFAULT false,
                created_at TIMESTAMP(0),
                updated_at TIMESTAMP(0),
                CONSTRAINT organizations_new_department_id_foreign 
                    FOREIGN KEY (department_id) 
                    REFERENCES departments(id) 
                    ON DELETE SET NULL
            )
        ');

        // Copy data to new table
        DB::statement('
            INSERT INTO organizations_new (id, name, org_acronym, type, department_id, status, has_system, created_at, updated_at)
            SELECT id, name, org_acronym, type, department_id, status, has_system, created_at, updated_at
            FROM organizations
        ');

        // Update sequence
        DB::statement('SELECT setval(\'organizations_new_id_seq\', (SELECT MAX(id) FROM organizations_new))');

        // Drop old table (this will cascade drop all foreign keys)
        DB::statement('DROP TABLE organizations CASCADE');

        // Rename new table
        DB::statement('ALTER TABLE organizations_new RENAME TO organizations');
        DB::statement('ALTER SEQUENCE organizations_new_id_seq RENAME TO organizations_id_seq');
        DB::statement('ALTER INDEX organizations_new_pkey RENAME TO organizations_pkey');

        // Recreate all foreign key constraints that were dropped
        $tables = [
            'organization_user',
            'organization_settings',
            'documents',
            'shared_documents',
            'announcements',
            'events',
            'payments',
            'elections',
            'candidates',
            'votes',
            'student_documents',
            'edushare_items',
            'library_resources',
            'complaints',
            'faqs',
            'student_payments',
            'admin_chats',
            'event_proposals',
            'event_financial_reports',
            'event_registrations',
            'edushare_folders'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'organization_id')) {
                DB::statement("
                    ALTER TABLE {$table} 
                    ADD CONSTRAINT {$table}_organization_id_foreign 
                    FOREIGN KEY (organization_id) 
                    REFERENCES organizations(id) 
                    ON DELETE CASCADE
                ");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('department_id');
            });

            return;
        }

        // Reorder columns back to original order
        DB::statement('
            CREATE TABLE organizations_old (
                id BIGSERIAL PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                type VARCHAR(255) NOT NULL,
                status VARCHAR(255) NOT NULL DEFAULT \'Pending\',
                created_at TIMESTAMP(0),
                updated_at TIMESTAMP(0),
                org_acronym VARCHAR(20),
                has_system BOOLEAN NOT NULL DEFAULT false
            )
        ');

        // Copy data back (without department_id)
        DB::statement('
            INSERT INTO organizations_old (id, name, type, status, created_at, updated_at, org_acronym, has_system)
            SELECT id, name, type, status, created_at, updated_at, org_acronym, has_system
            FROM organizations
        ');

        // Update sequence
        DB::statement('SELECT setval(\'organizations_old_id_seq\', (SELECT MAX(id) FROM organizations_old))');

        // Drop new table
        DB::statement('DROP TABLE organizations CASCADE');

        // Rename old table back
        DB::statement('ALTER TABLE organizations_old RENAME TO organizations');
        DB::statement('ALTER SEQUENCE organizations_old_id_seq RENAME TO organizations_id_seq');
        DB::statement('ALTER INDEX organizations_old_pkey RENAME TO organizations_pkey');

        // Recreate foreign key constraints
        $tables = [
            'organization_user',
            'organization_settings',
            'documents',
            'shared_documents',
            'announcements',
            'events',
            'payments',
            'elections',
            'candidates',
            'votes',
            'student_documents',
            'edushare_items',
            'library_resources',
            'complaints',
            'faqs',
            'student_payments',
            'admin_chats',
            'event_proposals',
            'event_financial_reports',
            'event_registrations',
            'edushare_folders'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'organization_id')) {
                DB::statement("
                    ALTER TABLE {$table} 
                    ADD CONSTRAINT {$table}_organization_id_foreign 
                    FOREIGN KEY (organization_id) 
                    REFERENCES organizations(id) 
                    ON DELETE CASCADE
                ");
            }
        }
    }
};
