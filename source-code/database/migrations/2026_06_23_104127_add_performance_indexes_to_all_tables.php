<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============ apply_orgs ============
        Schema::table('apply_orgs', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('reviewed_at');
            $table->index('status');
            $table->index('request_type');
            $table->index(['reviewed_by', 'reviewed_at']);
        });

        // ============ organizations ============
        Schema::table('organizations', function (Blueprint $table) {
            $table->index('name');
            $table->index('org_acronym');
            $table->index('type');
            $table->index('status');
            $table->index('department_id');
            $table->index('archived_at');
            $table->index('created_at');
        });

        // ============ events ============
        Schema::table('events', function (Blueprint $table) {
            $table->index('approved_by');
        });

        // ============ osas_chats ============
        Schema::table('osas_chats', function (Blueprint $table) {
            $table->index('organization_id');
            $table->index('sender_id');
            $table->index('is_from_osas');
            $table->index('read_at');
            $table->index('created_at');
            $table->index(['organization_id', 'created_at']);
        });

        // ============ osas_feedback ============
        Schema::table('osas_feedback', function (Blueprint $table) {
            $table->index('osas_id');
            $table->index('organization_id');
            $table->index('responded_by');
            $table->index('status');
            $table->index('priority');
            $table->index('created_at');
        });

        // ============ organization_user (pivot) ============
        Schema::table('organization_user', function (Blueprint $table) {
            $table->index('membership_status');
        });

        // ============ organization_settings ============
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->index('organization_id');
        });

        // ============ departments ============
        Schema::table('departments', function (Blueprint $table) {
            $table->index('department_acronym');
            $table->index('department_name');
        });
    }

    public function down(): void
    {
        Schema::table('apply_orgs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['reviewed_at']);
            $table->dropIndex(['status']);
            $table->dropIndex(['request_type']);
            $table->dropIndex(['reviewed_by', 'reviewed_at']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['org_acronym']);
            $table->dropIndex(['type']);
            $table->dropIndex(['status']);
            $table->dropIndex(['department_id']);
            $table->dropIndex(['archived_at']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['approved_by']);
        });

        Schema::table('osas_chats', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['sender_id']);
            $table->dropIndex(['is_from_osas']);
            $table->dropIndex(['read_at']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['organization_id', 'created_at']);
        });

        Schema::table('osas_feedback', function (Blueprint $table) {
            $table->dropIndex(['osas_id']);
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['responded_by']);
            $table->dropIndex(['status']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('organization_user', function (Blueprint $table) {
            $table->dropIndex(['membership_status']);
        });

        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropIndex(['department_acronym']);
            $table->dropIndex(['department_name']);
        });
    }
};
