<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Archive snapshot of an Approved apply_org per accreditation period.
     * Triggered by Start Renewal (ExpiredRenewalGenerator) BEFORE queuing
     * a Pending Renewal — so Document Library can still surface the old
     * docs via organization_id + accreditation_year even when the Workbench
     * card is hidden (no duplicate All/Recognition).
     */
    public function up(): void
    {
        Schema::create('apply_org_document_archives', function (Blueprint $table) {
            $table->id();
            // Links — keep organization history even if source row is later hidden/deleted
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('source_apply_org_id')->nullable()->constrained('apply_orgs')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();

            // Mirror of apply_orgs fillable columns (exact types, see ApplyOrg.php:16-48)
            $table->string('organization_name');
            $table->string('organization_acronym', 50)->nullable();
            $table->string('organization_type');
            $table->string('request_type')->default('request');
            $table->string('application_type')->nullable();
            $table->boolean('auto_generated')->default(false);
            $table->string('requester_first_name')->nullable();
            $table->string('requester_last_name')->nullable();
            $table->string('requester_email')->nullable();
            $table->string('requester_position')->nullable();

            // Document columns — same types as apply_orgs
            $table->string('accreditation_form')->nullable();
            $table->string('letter_of_application')->nullable();
            $table->string('constitution_bylaws')->nullable();
            $table->string('renewal_form')->nullable();
            $table->string('adviser_commitment')->nullable();
            $table->string('application_form')->nullable();
            $table->string('certification_of_officers')->nullable();
            $table->string('plan_of_activities')->nullable();
            $table->string('certification_dean')->nullable();
            $table->string('financial_report')->nullable();
            $table->text('list_of_members')->nullable()->comment('JSON array of file paths');
            $table->string('accomplishment_reports')->nullable();
            $table->text('other_files')->nullable();

            $table->integer('current_step')->default(1);
            $table->string('status')->default('Pending');
            $table->string('accreditation_year')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();

            $table->index('organization_id');
            $table->index('accreditation_year');
            $table->index('status');
            $table->index('request_type');
            $table->index(['organization_id', 'accreditation_year']);
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apply_org_document_archives');
    }
};
