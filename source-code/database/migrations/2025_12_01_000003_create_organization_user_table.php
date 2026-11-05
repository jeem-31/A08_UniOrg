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
        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->after('id')->constrained()->onDelete('cascade');
            $table->foreignId('organization_id')->after('user_id')->constrained()->onDelete('cascade');
            $table->string('role');
            $table->enum('membership_status', ['Active', 'Pending']);
            $table->timestamps();
            $table->index(['user_id', 'organization_id'], 'org_user_user_org_idx');
            $table->index('role', 'org_user_role_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_user');
    }
};
