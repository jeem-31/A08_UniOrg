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
        Schema::create('system_request_officers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_request_id')->constrained()->onDelete('cascade');
            $table->string('position'); // e.g., 'president', 'secretary_1', 'pio_1'
            $table->string('student_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('course')->nullable();
            $table->string('year_level')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_request_officers');
    }
};
