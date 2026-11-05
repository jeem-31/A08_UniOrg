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
        Schema::table('system_requests', function (Blueprint $table) {
            $table->dropColumn([
                'adviser_commitment_form',
                'program_of_activities',
                'list_of_officers_members'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_requests', function (Blueprint $table) {
            $table->string('adviser_commitment_form')->nullable();
            $table->string('program_of_activities')->nullable();
            $table->json('list_of_officers_members')->nullable();
        });
    }
};
