<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('dean_approved_at')->nullable()->after('approved_at');
            $table->string('osas_status')->nullable()->after('dean_approved_at');
            $table->foreignId('osas_approved_by')->nullable()->constrained('users')->onDelete('set null')->after('osas_status');
            $table->timestamp('osas_approved_at')->nullable()->after('osas_approved_by');
            $table->index(['osas_status', 'created_at'], 'events_osas_status_created_idx');
        });
    }

    public function down(): void
    {       
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_osas_status_created_idx');
            $table->dropForeign(['osas_approved_by']);
            $table->dropColumn(['dean_approved_at', 'osas_status', 'osas_approved_by', 'osas_approved_at']);
        });
    }
};
