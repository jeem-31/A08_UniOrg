<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authentication_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 40);
            $table->string('identifier')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('path', 120);
            $table->timestamp('occurred_at');
            $table->index(['occurred_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authentication_events');
    }
};
