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
        Schema::table('organization_settings', function (Blueprint $table) {
            // Add module columns after has_edushare
            $table->boolean('has_chatbot')->default(false)->after('has_edushare');
            $table->boolean('has_complaints')->default(false)->after('has_chatbot');
            $table->boolean('has_documents')->default(false)->after('has_complaints');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn(['has_chatbot', 'has_complaints', 'has_documents']);
        });
    }
};
