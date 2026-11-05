<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apply_orgs', function (Blueprint $table): void {
            if (! Schema::hasColumn('apply_orgs', 'auto_generated')) {
                $table->boolean('auto_generated')->default(false)->after('application_type');
                $table->index('auto_generated');
            }
        });
    }

    public function down(): void
    {
        Schema::table('apply_orgs', function (Blueprint $table): void {
            if (Schema::hasColumn('apply_orgs', 'auto_generated')) {
                $table->dropIndex(['auto_generated']);
                $table->dropColumn('auto_generated');
            }
        });
    }
};
