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
                // Drop all non-essential columns
                $table->dropColumn([
                    'last_name',
                    'first_name',
                    'middle_name',
                    'suffix',
                    'course',
                    'year_section'
                ]);
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
                $table->string('last_name')->nullable();
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('suffix')->nullable();
                $table->string('course')->nullable();
                $table->string('year_section')->nullable();
            });
        }
    }
};
