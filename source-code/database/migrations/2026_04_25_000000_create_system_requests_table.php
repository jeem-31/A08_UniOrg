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
        Schema::create('system_requests', function (Blueprint $table) {
            $table->id();
            $table->string('organization_name');
            $table->enum('organization_type', ['Academic', 'Non-Academic']);
            $table->string('requester_name');
            $table->string('requester_email');
            $table->enum('requester_position', ['Organization Adviser', 'Organization President']);
            $table->integer('expected_members')->nullable();
            $table->string('accreditation_form')->nullable(); // File path
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_requests');
    }
};
