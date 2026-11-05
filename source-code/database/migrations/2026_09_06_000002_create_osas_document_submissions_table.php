<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('osas_document_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('required_document_id')->constrained('osas_required_documents')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('storage_disk', 60);
            $table->string('original_filename');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            // submitted (waiting) -> approved_for_signature | rejected (admin may resubmit as new version)
            $table->string('status', 40)->default('submitted');
            $table->text('remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            // Link to the auto-generated summary row (processed_documents via report linker ApplyOrg).
            $table->unsignedBigInteger('processed_document_id')->nullable();
            $table->timestamps();

            $table->index(['required_document_id', 'organization_id', 'version']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('osas_document_submissions');
    }
};
