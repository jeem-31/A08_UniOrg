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
            // Add request type
            $table->enum('request_type', ['request', 'apply'])->default('request')->after('organization_type');
            
            // Add document fields for "Apply" type
            $table->string('letter_of_application')->nullable()->after('accreditation_form');
            $table->string('constitution_bylaws')->nullable()->after('letter_of_application');
            $table->string('renewal_form')->nullable()->after('constitution_bylaws');
            $table->string('program_of_activities')->nullable()->after('renewal_form');
            $table->string('adviser_commitment_form')->nullable()->after('program_of_activities');
            $table->string('certification_of_officers')->nullable()->after('adviser_commitment_form');
            $table->string('list_of_officers_members')->nullable()->after('certification_of_officers');
            
            // Add step tracking for multi-step form
            $table->integer('current_step')->default(1)->after('list_of_officers_members');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_requests', function (Blueprint $table) {
            $table->dropColumn([
                'request_type',
                'letter_of_application',
                'constitution_bylaws',
                'renewal_form',
                'program_of_activities',
                'adviser_commitment_form',
                'certification_of_officers',
                'list_of_officers_members',
                'current_step',
            ]);
        });
    }
};
