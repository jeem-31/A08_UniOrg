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
        if (Schema::hasTable('enrolled_students')) {
            Schema::table('enrolled_students', function (Blueprint $table) {
                if (Schema::hasColumn('enrolled_students', 'last_name')) {
                    $table->string('last_name')->nullable()->change();
                }
                if (Schema::hasColumn('enrolled_students', 'first_name')) {
                    $table->string('first_name')->nullable()->change();
                }
                if (Schema::hasColumn('enrolled_students', 'middle_name')) {
                    $table->string('middle_name')->nullable()->change();
                }
                if (Schema::hasColumn('enrolled_students', 'course')) {
                    $table->string('course')->nullable()->change();
                }
                if (Schema::hasColumn('enrolled_students', 'year_section')) {
                    $table->string('year_section')->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('enrolled_students')) {
            Schema::table('enrolled_students', function (Blueprint $table) {
                $table->string('last_name')->nullable(false)->change();
                $table->string('first_name')->nullable(false)->change();
                $table->string('middle_name')->nullable(false)->change();
                $table->string('course')->nullable(false)->change();
                $table->string('year_section')->nullable(false)->change();
            });
        }
    }
};
