<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('visibility')->default('all')->after('organization_id');
        });

        Schema::create('announcement_organization', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->onDelete('cascade');
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['announcement_id', 'organization_id'], 'announcement_org_unique');
            $table->index('organization_id', 'announcement_org_org_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_organization');

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
