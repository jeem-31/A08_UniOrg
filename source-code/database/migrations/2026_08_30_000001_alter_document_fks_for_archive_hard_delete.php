<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Make apply_org_id nullable + SET NULL so hard delete of source ApplyOrg
        // does not cascade-delete library entries — archived copies keep history per period
        try {
            DB::statement('ALTER TABLE document_library_entries DROP CONSTRAINT IF EXISTS document_library_entries_apply_org_id_foreign');
        } catch (\Throwable $e) {
        }
        // Make column nullable (pgsql needs raw)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_library_entries ALTER COLUMN apply_org_id DROP NOT NULL');
        } else {
            Schema::table('document_library_entries', function (Blueprint $table) {
                $table->foreignId('apply_org_id')->nullable()->change();
            });
        }
        Schema::table('document_library_entries', function (Blueprint $table) {
            $table->foreign('apply_org_id')->references('id')->on('apply_orgs')->nullOnDelete();
            if (! Schema::hasColumn('document_library_entries', 'archive_id')) {
                $table->foreignId('archive_id')->nullable()->after('apply_org_id')->constrained('apply_org_document_archives')->nullOnDelete();
            }
        });

        // processed_documents.apply_org_id (renamed from system_request_id, cascade)
        foreach (['processed_documents_apply_org_id_foreign', 'processed_documents_system_request_id_foreign'] as $fk) {
            try {
                DB::statement("ALTER TABLE processed_documents DROP CONSTRAINT IF EXISTS {$fk}");
            } catch (\Throwable $e) {
            }
        }
        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement('ALTER TABLE processed_documents ALTER COLUMN apply_org_id DROP NOT NULL');
            } catch (\Throwable $e) {
            }
        } else {
            try {
                Schema::table('processed_documents', function (Blueprint $table) {
                    $table->foreignId('apply_org_id')->nullable()->change();
                });
            } catch (\Throwable $e) {
            }
        }
        Schema::table('processed_documents', function (Blueprint $table) {
            try {
                $table->foreign('apply_org_id')->references('id')->on('apply_orgs')->nullOnDelete();
            } catch (\Throwable $e) {
            }
            if (! Schema::hasColumn('processed_documents', 'archive_id')) {
                $table->foreignId('archive_id')->nullable()->after('apply_org_id')->constrained('apply_org_document_archives')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE document_library_entries DROP CONSTRAINT IF EXISTS document_library_entries_archive_id_foreign');
        } catch (\Throwable $e) {
        }
        try {
            DB::statement('ALTER TABLE document_library_entries DROP CONSTRAINT IF EXISTS document_library_entries_apply_org_id_foreign');
        } catch (\Throwable $e) {
        }
        if (Schema::hasColumn('document_library_entries', 'archive_id')) {
            Schema::table('document_library_entries', function (Blueprint $table) {
                $table->dropColumn('archive_id');
            });
        }
        Schema::table('document_library_entries', function (Blueprint $table) {
            $table->foreign('apply_org_id')->references('id')->on('apply_orgs')->cascadeOnDelete();
        });

        try {
            DB::statement('ALTER TABLE processed_documents DROP CONSTRAINT IF EXISTS processed_documents_archive_id_foreign');
        } catch (\Throwable $e) {
        }
        try {
            DB::statement('ALTER TABLE processed_documents DROP CONSTRAINT IF EXISTS processed_documents_apply_org_id_foreign');
        } catch (\Throwable $e) {
        }
        try {
            DB::statement('ALTER TABLE processed_documents DROP CONSTRAINT IF EXISTS processed_documents_system_request_id_foreign');
        } catch (\Throwable $e) {
        }
        if (Schema::hasColumn('processed_documents', 'archive_id')) {
            Schema::table('processed_documents', function (Blueprint $table) {
                $table->dropColumn('archive_id');
            });
        }
        Schema::table('processed_documents', function (Blueprint $table) {
            $table->foreign('apply_org_id')->references('id')->on('apply_orgs')->cascadeOnDelete();
        });
    }
};
