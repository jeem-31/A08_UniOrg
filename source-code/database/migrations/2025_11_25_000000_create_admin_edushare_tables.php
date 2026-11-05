<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create folders table if not exists
        if (!Schema::hasTable('edushare_folders')) {
            Schema::create('edushare_folders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('parent_id')->nullable()->constrained('edushare_folders')->onDelete('cascade');
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'parent_id'], 'edushare_folders_user_parent_idx');
                $table->index('parent_id', 'edushare_folders_parent_id_idx');
            });
        }

        // Add folder_id to edushare_items if not exists
        if (!Schema::hasColumn('edushare_items', 'folder_id')) {
            Schema::table('edushare_items', function (Blueprint $table) {
                $table->foreignId('folder_id')->nullable()->after('user_id')->constrained('edushare_folders')->onDelete('cascade');
                $table->index('folder_id', 'edushare_items_folder_id_idx');
            });
        }

        // Create shares table if not exists
        if (!Schema::hasTable('edushare_shares')) {
            Schema::create('edushare_shares', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_id')->nullable()->constrained('edushare_items')->onDelete('cascade');
                $table->foreignId('folder_id')->nullable()->constrained('edushare_folders')->onDelete('cascade');
                $table->enum('share_type', ['everyone', 'specific']);
                $table->string('shared_with_email')->nullable();
                $table->foreignId('shared_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();
                $table->index(['item_id', 'share_type'], 'edushare_shares_item_type_idx');
                $table->index(['folder_id', 'share_type'], 'edushare_shares_folder_type_idx');
                $table->index('shared_with_email', 'edushare_shares_email_idx');
                $table->index('shared_by', 'edushare_shares_shared_by_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edushare_shares');
        Schema::table('edushare_items', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
            $table->dropColumn('folder_id');
        });
        Schema::dropIfExists('edushare_folders');
    }
};
