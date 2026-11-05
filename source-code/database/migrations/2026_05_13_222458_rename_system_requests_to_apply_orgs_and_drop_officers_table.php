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
        // 1. Drop the redundant officers table
        Schema::dropIfExists('system_request_officers');

        // 2. Rename system_requests to apply_orgs
        Schema::rename('system_requests', 'apply_orgs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('apply_orgs', 'system_requests');
        
        // Note: We cannot easily recreate the dropped table with its data in down()
        // But for a clean structure, we'll just focus on renaming back
    }
};
