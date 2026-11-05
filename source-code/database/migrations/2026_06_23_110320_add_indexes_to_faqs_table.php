<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->index('status');
            $table->index('is_published');
            $table->index('created_at');
            $table->index(['is_published', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['is_published']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['is_published', 'created_at']);
            $table->dropIndex(['status', 'created_at']);
        });
    }
};
