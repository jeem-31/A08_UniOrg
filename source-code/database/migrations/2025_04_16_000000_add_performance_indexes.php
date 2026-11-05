<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add indexes for announcements
        Schema::table('announcements', function (Blueprint $table) {
            // `status` is introduced by a later migration; use columns available at this point.
            // Keep the established name so deployments that have already run this migration
            // retain compatible rollback behavior.
            $table->index(['is_published', 'created_at'], 'announcements_status_published_created_idx');
            $table->index(['type', 'created_at'], 'announcements_type_created_idx');
            $table->index('user_id', 'announcements_user_id_idx');
        });

        // Add indexes only for event columns that exist at this migration point.
        // Approval columns are introduced and indexed by their later migrations.
        Schema::table('events', function (Blueprint $table) {
            $table->index(['is_published', 'created_at'], 'events_status_published_created_idx');
            $table->index('user_id', 'events_user_id_idx');
            $table->index(['start_date', 'end_date'], 'events_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex('announcements_status_published_created_idx');
            $table->dropIndex('announcements_type_created_idx');
            $table->dropIndex('announcements_user_id_idx');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_status_published_created_idx');
            $table->dropIndex('events_user_id_idx');
            $table->dropIndex('events_dates_idx');
        });
    }
};
