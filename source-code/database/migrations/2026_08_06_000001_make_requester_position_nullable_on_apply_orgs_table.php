<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The requester position is no longer required: the apply form may be
     * submitted without it, so the column must accept NULL.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE apply_orgs ALTER COLUMN requester_position DROP NOT NULL");
            return;
        }

        Schema::table('apply_orgs', function (Blueprint $table): void {
            $table->string('requester_position', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE apply_orgs ALTER COLUMN requester_position SET NOT NULL");
            return;
        }

        Schema::table('apply_orgs', function (Blueprint $table): void {
            $table->string('requester_position', 255)->nullable(false)->change();
        });
    }
};
