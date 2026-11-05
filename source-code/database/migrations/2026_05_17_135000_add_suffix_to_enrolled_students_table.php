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
        if (Schema::hasTable('enrolled_students') && !Schema::hasColumn('enrolled_students', 'suffix')) {
            Schema::table('enrolled_students', function (Blueprint $table) {
                $table->string('suffix')->nullable()->after('middle_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('enrolled_students') && Schema::hasColumn('enrolled_students', 'suffix')) {
            Schema::table('enrolled_students', function (Blueprint $table) {
                $table->dropColumn('suffix');
            });
        }
    }
};
