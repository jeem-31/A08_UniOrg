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
        Schema::table('payments', function (Blueprint $table) {
            // Drop the json qr_code column if it exists
            if (Schema::hasColumn('payments', 'qr_code')) {
                $table->dropColumn('qr_code');
            }
            // Add qr_code as string/text column
            $table->text('qr_code')->nullable()->after('gcash_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('qr_code');
        });
    }
};
