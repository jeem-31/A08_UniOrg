<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates the organization_role_permissions table which stores
     * additional (additive) module grants per role per organization.
     * 
     * This is layered ON TOP of the PermissionMatrix defaults —
     * it can only GRANT access, not revoke default access.
     */
    public function up(): void
    {
        Schema::create('organization_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('role'); // e.g. 'Secretary', 'Treasurer'
            $table->string('module'); // e.g. 'elections', 'payments'
            $table->boolean('is_granted')->default(true);
            $table->timestamps();

            // A role can only have one record per module per organization
            $table->unique(['organization_id', 'role', 'module']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_role_permissions');
    }
};
