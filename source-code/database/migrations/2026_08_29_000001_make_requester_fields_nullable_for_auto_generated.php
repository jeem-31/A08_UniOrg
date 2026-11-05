<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auto-generated renewal placeholders (ExpiredRenewalGenerator) have no
     * requester info yet — they are queued as Pending and later overwritten
     * by RenewController when the org uploads renewal docs. So requester
     * columns must accept NULL for auto_generated = true rows.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE apply_orgs ALTER COLUMN requester_first_name DROP NOT NULL');
            DB::statement('ALTER TABLE apply_orgs ALTER COLUMN requester_last_name DROP NOT NULL');
            DB::statement('ALTER TABLE apply_orgs ALTER COLUMN requester_email DROP NOT NULL');
            // requester_position already nullable via 2026_08_06, but ensure idempotent
            try {
                DB::statement('ALTER TABLE apply_orgs ALTER COLUMN requester_position DROP NOT NULL');
            } catch (\Throwable $e) {
                // already nullable
            }
            return;
        }

        Schema::table('apply_orgs', function (Blueprint $table): void {
            $table->string('requester_first_name', 255)->nullable()->change();
            $table->string('requester_last_name', 255)->nullable()->change();
            $table->string('requester_email', 255)->nullable()->change();
            $table->string('requester_position', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE apply_orgs ALTER COLUMN requester_first_name SET NOT NULL');
            DB::statement('ALTER TABLE apply_orgs ALTER COLUMN requester_last_name SET NOT NULL');
            DB::statement('ALTER TABLE apply_orgs ALTER COLUMN requester_email SET NOT NULL');
            DB::statement('ALTER TABLE apply_orgs ALTER COLUMN requester_position SET NOT NULL');
            return;
        }

        Schema::table('apply_orgs', function (Blueprint $table): void {
            $table->string('requester_first_name', 255)->nullable(false)->change();
            $table->string('requester_last_name', 255)->nullable(false)->change();
            $table->string('requester_email', 255)->nullable(false)->change();
            $table->string('requester_position', 255)->nullable(false)->change();
        });
    }
};
