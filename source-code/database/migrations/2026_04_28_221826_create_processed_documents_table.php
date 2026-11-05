<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_request_id')->constrained('system_requests')->onDelete('cascade');
            $table->string('document_field'); // Which field: accreditation_form, letter_of_application, etc.
            $table->string('original_filename');
            $table->string('file_path'); // Path in Cloudflare R2
            $table->string('document_type')->nullable(); // Detected type: letter_of_application, constitution, etc.
            $table->string('document_type_name')->nullable(); // Human-readable name
            $table->text('summary')->nullable();
            $table->json('tags')->nullable();
            $table->json('key_information')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable(); // 0-100
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('system_request_id');
            $table->index('document_type');
            $table->index('processing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_documents');
    }
};
