<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_id')->nullable()->constrained('student_payments')->onDelete('set null');
            $table->enum('status', ['registered', 'paid', 'cancelled'])->default('registered');
            $table->timestamp('registered_at');
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index('status', 'event_registrations_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
