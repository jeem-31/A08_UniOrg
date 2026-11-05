<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite cannot alter table constraints; the portable test schema keeps
        // the column definition as-is and relies on application validation.
        if (! $this->supportsCheckConstraintAlteration()) {
            return;
        }

        // PostgreSQL compatible enum modification
        DB::statement('ALTER TABLE student_documents DROP CONSTRAINT IF EXISTS student_documents_document_type_check');
        DB::statement("ALTER TABLE student_documents ADD CONSTRAINT student_documents_document_type_check CHECK (document_type IN ('cor', 'medical_certificate', 'parent_consent'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! $this->supportsCheckConstraintAlteration()) {
            return;
        }

        // Revert to original enum values
        DB::statement('ALTER TABLE student_documents DROP CONSTRAINT IF EXISTS student_documents_document_type_check');
        DB::statement("ALTER TABLE student_documents ADD CONSTRAINT student_documents_document_type_check CHECK (document_type IN ('medical_certificate', 'parent_consent', 'tour_requirements', 'other'))");
    }

    private function supportsCheckConstraintAlteration(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }
};
