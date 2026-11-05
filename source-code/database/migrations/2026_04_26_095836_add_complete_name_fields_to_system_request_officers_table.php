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
        Schema::table('system_request_officers', function (Blueprint $table) {
            // Add complete name fields for accurate identification
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('middle_initial')->nullable()->after('middle_name');
            $table->string('suffix')->nullable()->after('last_name');
            
            // Add section for more specific student identification
            $table->string('section')->nullable()->after('year_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_request_officers', function (Blueprint $table) {
            $table->dropColumn(['middle_name', 'middle_initial', 'suffix', 'section']);
        });
    }
};
