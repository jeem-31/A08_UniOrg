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
        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->after('id')->constrained()->onDelete('cascade');
            $table->string('theme_color')->nullable();
            $table->string('logo_url')->nullable();
            $table->boolean('has_announcements')->default(true);
            $table->boolean('has_events')->default(true);
            $table->boolean('has_payments')->default(true);
            $table->boolean('has_elections')->default(true);
            $table->boolean('has_elibrary')->default(true);
            $table->boolean('has_edushare')->default(true);
            $table->text('welcome_text')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
