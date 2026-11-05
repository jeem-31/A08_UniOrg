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
            // Tracks an OSAS-gated request to change an already OSAS-approved
            // event's date/time/venue (e.g. due to a typhoon or venue issue).
            $table->string('reschedule_status')->nullable()->after('osas_reviewed_at');
            $table->text('reschedule_reason')->nullable()->after('reschedule_status');
            $table->foreignId('reschedule_requested_by')->nullable()->after('reschedule_reason')->constrained('users')->onDelete('set null');
            $table->timestamp('reschedule_requested_at')->nullable()->after('reschedule_requested_by');
            $table->foreignId('reschedule_reviewed_by')->nullable()->after('reschedule_requested_at')->constrained('users')->onDelete('set null');
            $table->timestamp('reschedule_reviewed_at')->nullable()->after('reschedule_reviewed_by');
            $table->text('reschedule_rejection_reason')->nullable()->after('reschedule_reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_proposals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reschedule_requested_by');
            $table->dropConstrainedForeignId('reschedule_reviewed_by');
            $table->dropColumn([
                'reschedule_status',
                'reschedule_reason',
                'reschedule_requested_at',
                'reschedule_reviewed_at',
                'reschedule_rejection_reason',
            ]);
        });
    }
};
