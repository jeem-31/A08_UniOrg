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
        if (Schema::hasTable('system_requests')) {
            Schema::table('system_requests', function (Blueprint $table) {
                // Add organization acronym if not exists
                if (!Schema::hasColumn('system_requests', 'organization_acronym')) {
                    $table->string('organization_acronym', 50)->nullable()->after('organization_name');
                }
                
                // Add department_id if not exists
                if (!Schema::hasColumn('system_requests', 'department_id')) {
                    $table->foreignId('department_id')->nullable()->after('organization_type')->constrained('departments')->onDelete('set null');
                }
                
                // Add application_type if not exists
                if (!Schema::hasColumn('system_requests', 'application_type')) {
                    $table->enum('application_type', ['apply', 'renew'])->nullable()->after('request_type');
                }
                
                // Add new document fields if not exists
                if (!Schema::hasColumn('system_requests', 'adviser_commitment')) {
                    $table->string('adviser_commitment')->nullable()->after('accreditation_form');
                }
                
                if (!Schema::hasColumn('system_requests', 'application_form')) {
                    $table->string('application_form')->nullable()->after('adviser_commitment');
                }
                
                if (!Schema::hasColumn('system_requests', 'plan_of_activities')) {
                    $table->string('plan_of_activities')->nullable()->after('application_form');
                }
                
                if (!Schema::hasColumn('system_requests', 'certification_dean')) {
                    $table->string('certification_dean')->nullable()->after('certification_of_officers');
                }
                
                if (!Schema::hasColumn('system_requests', 'financial_report')) {
                    $table->string('financial_report')->nullable()->after('certification_dean');
                }
                
                if (!Schema::hasColumn('system_requests', 'list_of_members')) {
                    $table->text('list_of_members')->nullable()->after('financial_report')->comment('JSON array of file paths');
                }
                
                if (!Schema::hasColumn('system_requests', 'accomplishment_reports')) {
                    $table->string('accomplishment_reports')->nullable()->after('list_of_members');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_requests', function (Blueprint $table) {
            // Drop columns in reverse order
            $columns = [
                'accomplishment_reports',
                'list_of_members',
                'financial_report',
                'certification_dean',
                'plan_of_activities',
                'application_form',
                'adviser_commitment',
                'application_type',
                'organization_acronym',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('system_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
            
            // Drop foreign key and column for department_id
            if (Schema::hasColumn('system_requests', 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            }
        });
    }
};
