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
        Schema::table('student_documents', function (Blueprint $table) {
            $table->string('year_level')->nullable()->after('document_type');
            $table->enum('semester', ['1st Semester', '2nd Semester'])->nullable()->after('year_level');
            $table->index(['year_level', 'semester'], 'student_documents_year_semester_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_documents', function (Blueprint $table) {
            $table->dropIndex('student_documents_year_semester_idx');
            $table->dropColumn(['year_level', 'semester']);
        });
    }
};
