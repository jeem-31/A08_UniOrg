<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->decimal('planned_budget', 10, 2)->nullable()->after('venue');
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending')->after('planned_budget');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null')->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->index(['approval_status', 'created_at'], 'events_approval_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_approval_created_idx');
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['planned_budget', 'approval_status', 'approved_by', 'approved_at']);
        });
    }
};
