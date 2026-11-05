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
        Schema::table('processed_documents', function (Blueprint $table) {
            $table->renameColumn('system_request_id', 'apply_org_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processed_documents', function (Blueprint $table) {
            $table->renameColumn('apply_org_id', 'system_request_id');
        });
    }
};
