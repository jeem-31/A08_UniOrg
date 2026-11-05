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
        // ONLY remove description from edushare tables - NOTHING ELSE!
        if (Schema::hasColumn('edushare_items', 'description')) {
            Schema::table('edushare_items', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
        
        if (Schema::hasColumn('edushare_folders', 'description')) {
            Schema::table('edushare_folders', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back description columns if needed
        if (!Schema::hasColumn('edushare_items', 'description')) {
            Schema::table('edushare_items', function (Blueprint $table) {
                $table->text('description')->nullable()->after('title');
            });
        }
        
        if (!Schema::hasColumn('edushare_folders', 'description')) {
            Schema::table('edushare_folders', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });
        }
    }
};
