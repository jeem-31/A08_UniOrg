<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $this->requireSupportedDriver();

        Schema::create('account_management_audits', function (Blueprint $table): void {
            $table->id();
            $table->enum('action', self::ACTIONS);
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('actor_display');
            $table->foreignId('affected_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('affected_display');
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();
            $table->string('organization_display')->nullable();
            $table->json('changes');
            $table->timestampTz('occurred_at')->useCurrent();
        });

        $this->createNewestFirstIndexes();
        $this->createAppendOnlyProtection();
    }

    public function down(): void
    {
        $this->requireSupportedDriver();
        $this->dropAppendOnlyProtection();

        Schema::dropIfExists('account_management_audits');
    }

    private function createNewestFirstIndexes(): void
    {
        foreach ([
            'account_management_audits_newest_idx' => 'occurred_at DESC, id DESC',
            'account_management_audits_action_newest_idx' => 'action, occurred_at DESC, id DESC',
            'account_management_audits_actor_newest_idx' => 'actor_user_id, occurred_at DESC, id DESC',
            'account_management_audits_affected_newest_idx' => 'affected_user_id, occurred_at DESC, id DESC',
            'account_management_audits_organization_newest_idx' => 'organization_id, occurred_at DESC, id DESC',
        ] as $indexName => $columns) {
            DB::statement("CREATE INDEX {$indexName} ON account_management_audits ({$columns})");
        }
    }

    private function createAppendOnlyProtection(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(
                'DROP TRIGGER IF EXISTS account_management_audits_append_only_trigger ON account_management_audits'
            );
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_account_management_audits_mutation()');

            DB::unprepared(<<<'SQL'
                CREATE FUNCTION prevent_account_management_audits_mutation()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF TG_OP = 'DELETE' THEN
                        RAISE EXCEPTION 'account_management_audits records are append-only';
                    END IF;

                    IF NEW.id IS DISTINCT FROM OLD.id
                        OR NEW.action IS DISTINCT FROM OLD.action
                        OR NEW.actor_display IS DISTINCT FROM OLD.actor_display
                        OR NEW.affected_display IS DISTINCT FROM OLD.affected_display
                        OR NEW.organization_display IS DISTINCT FROM OLD.organization_display
                        OR NEW.changes::text IS DISTINCT FROM OLD.changes::text
                        OR NEW.occurred_at IS DISTINCT FROM OLD.occurred_at
                        OR (
                            NEW.actor_user_id IS DISTINCT FROM OLD.actor_user_id
                            AND NOT (OLD.actor_user_id IS NOT NULL AND NEW.actor_user_id IS NULL)
                        )
                        OR (
                            NEW.affected_user_id IS DISTINCT FROM OLD.affected_user_id
                            AND NOT (OLD.affected_user_id IS NOT NULL AND NEW.affected_user_id IS NULL)
                        )
                        OR (
                            NEW.organization_id IS DISTINCT FROM OLD.organization_id
                            AND NOT (OLD.organization_id IS NOT NULL AND NEW.organization_id IS NULL)
                        )
                    THEN
                        RAISE EXCEPTION 'account_management_audits records are append-only';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER account_management_audits_append_only_trigger
                BEFORE UPDATE OR DELETE ON account_management_audits
                FOR EACH ROW
                EXECUTE FUNCTION prevent_account_management_audits_mutation();
                SQL);

            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER account_management_audits_prevent_updates_trigger
            BEFORE UPDATE ON account_management_audits
            FOR EACH ROW
            WHEN NEW.id IS NOT OLD.id
                OR NEW.action IS NOT OLD.action
                OR NEW.actor_display IS NOT OLD.actor_display
                OR NEW.affected_display IS NOT OLD.affected_display
                OR NEW.organization_display IS NOT OLD.organization_display
                OR NEW.changes IS NOT OLD.changes
                OR NEW.occurred_at IS NOT OLD.occurred_at
                OR (
                    NEW.actor_user_id IS NOT OLD.actor_user_id
                    AND NOT (OLD.actor_user_id IS NOT NULL AND NEW.actor_user_id IS NULL)
                )
                OR (
                    NEW.affected_user_id IS NOT OLD.affected_user_id
                    AND NOT (OLD.affected_user_id IS NOT NULL AND NEW.affected_user_id IS NULL)
                )
                OR (
                    NEW.organization_id IS NOT OLD.organization_id
                    AND NOT (OLD.organization_id IS NOT NULL AND NEW.organization_id IS NULL)
                )
            BEGIN
                SELECT RAISE(ABORT, 'account_management_audits records are append-only');
            END;

            CREATE TRIGGER account_management_audits_prevent_deletes_trigger
            BEFORE DELETE ON account_management_audits
            FOR EACH ROW
            BEGIN
                SELECT RAISE(ABORT, 'account_management_audits records are append-only');
            END;
            SQL);
    }

    private function dropAppendOnlyProtection(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(
                'DROP TRIGGER IF EXISTS account_management_audits_append_only_trigger ON account_management_audits'
            );
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_account_management_audits_mutation()');

            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS account_management_audits_prevent_updates_trigger');
        DB::unprepared('DROP TRIGGER IF EXISTS account_management_audits_prevent_deletes_trigger');
    }

    private function requireSupportedDriver(): void
    {
        if (! in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException(
                'Account-management audit immutability requires PostgreSQL or SQLite triggers.',
            );
        }
    }
};
