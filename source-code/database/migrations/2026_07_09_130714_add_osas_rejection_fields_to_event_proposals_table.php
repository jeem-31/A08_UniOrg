<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_proposals', function (Blueprint $table) {
            $table->text('osas_rejection_reason')->nullable()->after('rejection_reason');
            $table->foreignId('osas_reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->onDelete('set null');
            $table->timestamp('osas_reviewed_at')->nullable()->after('osas_reviewed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_proposals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('osas_reviewed_by');
            $table->dropColumn(['osas_rejection_reason', 'osas_reviewed_at']);
        });
    }
};
