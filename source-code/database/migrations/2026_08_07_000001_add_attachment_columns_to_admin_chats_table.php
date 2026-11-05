<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add optional attachment metadata to admin_chats.
 *
 * OSAS <-> organization chat messages can now carry a single uploaded file
 * (image or document). The binary lives on the configured storage disk; only
 * the path and a few presentation hints are persisted so history stays
 * lightweight. Attachments live on admin_chats because OSAS conversations were
 * merged into the admin_chats table (channel = 'osas') by the
 * merge_osas_chat_into_admin_chats migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_chats', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('message');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime')->nullable()->after('attachment_name');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
        });
    }

    public function down(): void
    {
        Schema::table('admin_chats', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size']);
        });
    }
};
