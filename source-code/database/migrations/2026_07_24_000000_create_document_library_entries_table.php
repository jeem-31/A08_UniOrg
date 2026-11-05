<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_library_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apply_org_id')
                ->constrained('apply_orgs')
                ->cascadeOnDelete();
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('requirement_label');
            $table->string('original_filename');
            $table->string('storage_disk');
            $table->string('file_path');
            $table->uuid('source_version')->nullable();
            $table->char('source_fingerprint', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['apply_org_id', 'document_field', 'document_slot'],
                'dle_apply_field_slot_uq'
            );
            $table->index(['apply_org_id', 'id'], 'dle_apply_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_library_entries');
    }
};
