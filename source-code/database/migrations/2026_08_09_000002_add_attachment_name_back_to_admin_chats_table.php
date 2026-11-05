<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restore the original client filename for chat attachments.
 *
 * The stored object key is a random hash, so the original filename must be
 * persisted to display file names in the conversation (files render as a
 * named download chip; images render as inline thumbnails without a name).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_chats', function (Blueprint $table) {
            $table->string('attachment_name')->nullable()->after('attachment_path');
        });
    }

    public function down(): void
    {
        Schema::table('admin_chats', function (Blueprint $table) {
            $table->dropColumn('attachment_name');
        });
    }
};
