<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_resources', function (Blueprint $table) {
            $table->enum('resource_type', ['pdf', 'link', 'presentation'])->default('pdf')->after('file_type');
            $table->string('url')->nullable()->after('file_path');
            $table->index('resource_type', 'library_resources_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('library_resources', function (Blueprint $table) {
            $table->dropIndex('library_resources_type_idx');
            $table->dropColumn(['resource_type', 'url']);
        });
    }
};
