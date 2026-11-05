<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Archived library entries — same columns as document_library_entries + archive_id
        Schema::create('archived_document_library_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_id')->constrained('apply_org_document_archives')->cascadeOnDelete();
            $table->foreignId('original_apply_org_id')->nullable()->constrained('apply_orgs')->nullOnDelete();
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('requirement_label');
            $table->string('original_filename');
            $table->string('storage_disk');
            $table->string('file_path');
            $table->uuid('source_version')->nullable();
            $table->char('source_fingerprint', 64)->nullable();
            $table->timestamps();
            $table->unique(['archive_id', 'document_field', 'document_slot'], 'adle_archive_field_slot_uq');
            $table->index(['archive_id', 'id'], 'adle_archive_id_idx');
        });

        // Archived processed documents — full copy of processed_documents per period
        Schema::create('archived_processed_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_id')->constrained('apply_org_document_archives')->cascadeOnDelete();
            $table->foreignId('original_apply_org_id')->nullable()->constrained('apply_orgs')->nullOnDelete();
            $table->foreignId('original_processed_document_id')->nullable()->constrained('processed_documents')->nullOnDelete();
            $table->string('document_field');
            $table->string('document_slot')->default('primary');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('storage_disk')->nullable();
            $table->uuid('source_version')->nullable();
            $table->char('source_fingerprint', 64)->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_type_name')->nullable();
            $table->text('summary')->nullable();
            $table->json('tags')->nullable();
            $table->json('key_information')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('association_ready_at')->nullable();
            $table->uuid('active_cycle_id')->nullable();
            $table->string('cycle_kind', 20)->nullable();
            $table->unsignedTinyInteger('cycle_attempt_count')->default(0);
            $table->timestamp('processing_started_at')->nullable();
            $table->unsignedBigInteger('processing_duration_ms')->nullable();
            $table->uuid('output_source_version')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->unsignedInteger('summary_version')->default(1);
            $table->timestamp('summary_processed_at')->nullable();
            $table->string('resummarization_status', 20)->default('idle');
            $table->uuid('resummarization_cycle_id')->nullable();
            $table->text('resummarization_error_message')->nullable();
            $table->boolean('last_resummarization_changed')->nullable();
            $table->timestamps();
            $table->unique(['archive_id', 'document_field', 'document_slot'], 'apd_archive_field_slot_uq');
            $table->index(['archive_id', 'processing_status'], 'apd_archive_status_idx');
        });

        // Archived processing attempts — full copy of document_processing_attempts
        Schema::create('archived_processing_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archived_processed_document_id')->constrained('archived_processed_documents')->cascadeOnDelete();
            $table->foreignId('original_attempt_id')->nullable()->constrained('document_processing_attempts')->nullOnDelete();
            $table->uuid('cycle_id');
            $table->uuid('source_version');
            $table->string('operation', 20);
            $table->unsignedTinyInteger('ordinal');
            $table->string('outcome', 20)->default('running');
            $table->string('failure_class', 64)->nullable();
            $table->string('provider_status_code', 64)->nullable();
            $table->text('sanitized_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->timestamps();
            $table->unique(['archived_processed_document_id', 'cycle_id', 'ordinal'], 'apda_doc_cycle_ordinal_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_processing_attempts');
        Schema::dropIfExists('archived_processed_documents');
        Schema::dropIfExists('archived_document_library_entries');
    }
};
