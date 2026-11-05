<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the redundant chat attachment metadata columns.
 *
 * Only attachment_path is persisted: the stored path already carries the
 * extension (and the R2 object key), so presentation hints (mime, size) and
 * the original client filename are derived from the file itself when needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_chats', function (Blueprint $table) {
            $table->dropColumn(['attachment_name', 'attachment_mime', 'attachment_size']);
        });
    }

    public function down(): void
    {
        Schema::table('admin_chats', function (Blueprint $table) {
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime')->nullable()->after('attachment_name');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
        });
    }
};
