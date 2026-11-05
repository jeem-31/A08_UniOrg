<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->id();
            // One fingerprint per account only (change = delete+create)
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('credential_id', 1024)->unique();
            $table->text('public_key');
            $table->unsignedInteger('counter')->default(0);
            $table->json('transports')->nullable();
            $table->string('device_name')->nullable();
            $table->string('aaguid')->nullable();
            $table->timestamps();

            $table->index('credential_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');
    }
};
