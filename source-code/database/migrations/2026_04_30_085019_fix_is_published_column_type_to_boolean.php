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
        // PostgreSQL-only column type conversion. SQLite has no strict boolean
        // type and rejects ALTER COLUMN, so the portable schema is left as-is.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Fix is_published column type for PostgreSQL
        // Convert integer values to boolean
        
        // Announcements
        if (Schema::hasTable('announcements') && Schema::hasColumn('announcements', 'is_published')) {
            DB::statement('ALTER TABLE announcements ALTER COLUMN is_published TYPE BOOLEAN USING is_published::boolean');
        }
        
        // Events
        if (Schema::hasTable('events') && Schema::hasColumn('events', 'is_published')) {
            DB::statement('ALTER TABLE events ALTER COLUMN is_published TYPE BOOLEAN USING is_published::boolean');
        }
        
        // Library Resources
        if (Schema::hasTable('library_resources') && Schema::hasColumn('library_resources', 'is_published')) {
            DB::statement('ALTER TABLE library_resources ALTER COLUMN is_published TYPE BOOLEAN USING is_published::boolean');
        }
        
        // FAQs
        if (Schema::hasTable('faqs') && Schema::hasColumn('faqs', 'is_published')) {
            DB::statement('ALTER TABLE faqs ALTER COLUMN is_published TYPE BOOLEAN USING is_published::boolean');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Revert back to integer if needed (for rollback)
        
        // Announcements
        if (Schema::hasTable('announcements') && Schema::hasColumn('announcements', 'is_published')) {
            DB::statement('ALTER TABLE announcements ALTER COLUMN is_published TYPE INTEGER USING is_published::integer');
        }
        
        // Events
        if (Schema::hasTable('events') && Schema::hasColumn('events', 'is_published')) {
            DB::statement('ALTER TABLE events ALTER COLUMN is_published TYPE INTEGER USING is_published::integer');
        }
        
        // Library Resources
        if (Schema::hasTable('library_resources') && Schema::hasColumn('library_resources', 'is_published')) {
            DB::statement('ALTER TABLE library_resources ALTER COLUMN is_published TYPE INTEGER USING is_published::integer');
        }
        
        // FAQs
        if (Schema::hasTable('faqs') && Schema::hasColumn('faqs', 'is_published')) {
            DB::statement('ALTER TABLE faqs ALTER COLUMN is_published TYPE INTEGER USING is_published::integer');
        }
    }
};
