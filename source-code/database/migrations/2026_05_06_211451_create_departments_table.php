<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('department_name');
            $table->string('department_acronym', 20);
            $table->timestamps();
        });

        // Insert fixed department data
        DB::table('departments')->insert([
            ['department_name' => 'College of Engineering', 'department_acronym' => 'COE', 'created_at' => now(), 'updated_at' => now()],
            ['department_name' => 'College of International Hospitality and Tourism Management', 'department_acronym' => 'CIHTM', 'created_at' => now(), 'updated_at' => now()],
            ['department_name' => 'College of Nursing & Allied Health', 'department_acronym' => 'CONAH', 'created_at' => now(), 'updated_at' => now()],
            ['department_name' => 'College of Business Administration and Accountancy', 'department_acronym' => 'CBAA', 'created_at' => now(), 'updated_at' => now()],
            ['department_name' => 'College of Industrial Technology', 'department_acronym' => 'CIT', 'created_at' => now(), 'updated_at' => now()],
            ['department_name' => 'College of Teacher Education', 'department_acronym' => 'CTE', 'created_at' => now(), 'updated_at' => now()],
            ['department_name' => 'College of Computer Studies', 'department_acronym' => 'CCS', 'created_at' => now(), 'updated_at' => now()],
            ['department_name' => 'College of Arts & Sciences', 'department_acronym' => 'CAS', 'created_at' => now(), 'updated_at' => now()],
            ['department_name' => 'College of Criminal Justice Education', 'department_acronym' => 'CCJE', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
