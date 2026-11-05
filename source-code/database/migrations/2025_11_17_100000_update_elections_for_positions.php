<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add new columns to elections table
        Schema::table('elections', function (Blueprint $table) {
            if (!Schema::hasColumn('elections', 'is_posted')) {
                $table->boolean('is_posted')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('elections', 'secretary_id')) {
                $table->foreignId('secretary_id')->nullable()->constrained('candidates')->onDelete('set null')->after('results_published');
            }
        });

        // Update candidates table - position will store specific positions
        Schema::table('candidates', function (Blueprint $table) {
            if (!Schema::hasColumn('candidates', 'student_id')) {
                $table->string('student_id')->nullable()->after('name');
            }
            if (!Schema::hasColumn('candidates', 'year_level')) {
                $table->string('year_level')->nullable()->after('position');
            }
        });
    }

    public function down(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            $table->dropForeign(['secretary_id']);
            $table->dropColumn(['is_posted', 'secretary_id']);
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['student_id', 'year_level']);
        });
    }
};
