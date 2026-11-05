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
        if (! Schema::hasTable('admin_chats')) {
            return;
        }

        Schema::table('admin_chats', function (Blueprint $table) {
            // Single column indexes for frequently queried fields
            $table->index('user_id', 'admin_chats_user_id_idx');
            $table->index('sender_type', 'admin_chats_sender_type_idx');
            $table->index('is_read', 'admin_chats_is_read_idx');
            $table->index('created_at', 'admin_chats_created_at_idx');
            
            // Composite indexes for common query combinations
            // For: WHERE user_id = ? AND sender_type = ? AND is_read = ?
            $table->index(['user_id', 'sender_type', 'is_read'], 'admin_chats_user_sender_read_idx');
            
            // For: WHERE sender_type = ? AND is_read = ?
            $table->index(['sender_type', 'is_read'], 'admin_chats_sender_read_idx');
            
            // For: WHERE user_id = ? ORDER BY created_at
            $table->index(['user_id', 'created_at'], 'admin_chats_user_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('admin_chats')) {
            return;
        }

        Schema::table('admin_chats', function (Blueprint $table) {
            $table->dropIndex('admin_chats_user_id_idx');
            $table->dropIndex('admin_chats_sender_type_idx');
            $table->dropIndex('admin_chats_is_read_idx');
            $table->dropIndex('admin_chats_created_at_idx');
            $table->dropIndex('admin_chats_user_sender_read_idx');
            $table->dropIndex('admin_chats_sender_read_idx');
            $table->dropIndex('admin_chats_user_created_idx');
        });
    }
};
