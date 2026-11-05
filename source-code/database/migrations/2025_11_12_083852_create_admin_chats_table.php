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
        Schema::create('admin_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('message');
            $table->enum('sender_type', ['student', 'admin'])->default('student');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index('user_id', 'admin_chats_user_id_idx');
            $table->index('sender_type', 'admin_chats_sender_type_idx');
            $table->index('is_read', 'admin_chats_is_read_idx');
            $table->index('created_at', 'admin_chats_created_at_idx');
            $table->index(['user_id', 'sender_type', 'is_read'], 'admin_chats_user_sender_read_idx');
            $table->index(['sender_type', 'is_read'], 'admin_chats_sender_read_idx');
            $table->index(['user_id', 'created_at'], 'admin_chats_user_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_chats');
    }
};
