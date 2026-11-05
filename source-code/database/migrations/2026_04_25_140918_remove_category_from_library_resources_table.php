<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('library_resources', 'category')) {
            return;
        }

        // SQLite refuses to drop a column that a surviving index references, so
        // remove the dependent index first. PostgreSQL tolerates the same call.
        DB::statement('DROP INDEX IF EXISTS library_resources_category_created_idx');

        Schema::table('library_resources', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('library_resources', function (Blueprint $table) {
            $table->string('category')->nullable()->after('description');
        });
    }
};
