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
        Schema::create('osas_feedback', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('osas_id')->constrained('users')->onDelete('cascade');
            $blueprint->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $blueprint->string('subject');
            $blueprint->text('message');
            $blueprint->enum('priority', ['normal', 'urgent', 'critical'])->default('normal');
            $blueprint->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $blueprint->text('admin_response')->nullable();
            $blueprint->foreignId('responded_by')->nullable()->constrained('users')->onDelete('set null');
            $blueprint->timestamp('responded_at')->nullable();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('osas_feedback');
    }
};
