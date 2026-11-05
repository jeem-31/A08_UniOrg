<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            if (!Schema::hasColumn('candidates', 'student_id')) {
                $table->string('student_id')->nullable()->after('name');
            }
            if (!Schema::hasColumn('candidates', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('student_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            if (Schema::hasColumn('candidates', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('candidates', 'student_id')) {
                $table->dropColumn('student_id');
            }
        });
    }
};
