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
            $table->string('primary_color', 7)->nullable()->after('theme_color')->comment('Main brand color (e.g., #8238B3)');
            $table->string('complementary_color', 7)->nullable()->after('primary_color')->comment('Accent color for icons (e.g., #D7AC28)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'complementary_color']);
        });
    }
};
