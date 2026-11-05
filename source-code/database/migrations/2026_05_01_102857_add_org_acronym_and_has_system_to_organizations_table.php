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
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('org_acronym', 20)->nullable()->after('name')->comment('Organization acronym (e.g., SOCCS, JPCS)');
            $table->boolean('has_system')->default(false)->after('org_acronym')->comment('Whether the organization has access to the system');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['org_acronym', 'has_system']);
        });
    }
};
