<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apply_orgs', function (Blueprint $table) {
            $table->text('other_files')->nullable()->after('accomplishment_reports');
        });
    }

    public function down(): void
    {
        Schema::table('apply_orgs', function (Blueprint $table) {
            $table->dropColumn('other_files');
        });
    }
};
