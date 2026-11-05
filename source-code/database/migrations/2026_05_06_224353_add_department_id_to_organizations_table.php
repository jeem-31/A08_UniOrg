<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('organizations', 'department_id')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')->nullable()->after('type');
                $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
