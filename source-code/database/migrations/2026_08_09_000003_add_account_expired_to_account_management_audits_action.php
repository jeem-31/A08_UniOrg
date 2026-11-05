<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ACTIONS = [
        'account.created',
        'profile.updated',
        'role.changed',
        'account.deactivated',
        'account.reactivated',
        'account.expired',
        'account.deleted',
        'dean.assigned',
        'dean.replaced',
        'adviser.appointed',
        'officer.appointed',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE account_management_audits DROP CONSTRAINT account_management_audits_action_check');

        $values = collect(self::ACTIONS)
            ->map(fn (string $action): string => DB::getPdo()->quote($action))
            ->implode(', ');

        DB::statement(
            "ALTER TABLE account_management_audits ADD CONSTRAINT account_management_audits_action_check "
            ."CHECK (action IN ($values))"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $values = collect([
            'account.created',
            'profile.updated',
            'role.changed',
            'account.deactivated',
            'account.reactivated',
            'account.deleted',
            'dean.assigned',
            'dean.replaced',
            'adviser.appointed',
            'officer.appointed',
        ])->map(fn (string $action): string => DB::getPdo()->quote($action))
            ->implode(', ');

        DB::statement('ALTER TABLE account_management_audits DROP CONSTRAINT account_management_audits_action_check');
        DB::statement(
            "ALTER TABLE account_management_audits ADD CONSTRAINT account_management_audits_action_check "
            ."CHECK (action IN ($values))"
        );
    }
};
