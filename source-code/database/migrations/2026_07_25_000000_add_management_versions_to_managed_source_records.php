<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['users', 'organizations', 'organization_user'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('management_version')->default(1);
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->index(['role', 'is_active', 'id'], 'users_role_active_id_idx');
        });
        Schema::table('organization_user', function (Blueprint $table): void {
            $table->index(
                ['organization_id', 'membership_status', 'role', 'user_id'],
                'org_user_active_assignment_lookup_idx',
            );
        });

        foreach (self::TABLES as $tableName) {
            $this->createVersionTrigger($tableName);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            $this->dropVersionTrigger($tableName);
        }

        Schema::table('organization_user', function (Blueprint $table): void {
            $table->dropIndex('org_user_active_assignment_lookup_idx');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_role_active_id_idx');
        });

        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('management_version');
            });
        }
    }

    private function createVersionTrigger(string $tableName): void
    {
        $triggerName = "{$tableName}_management_version_trigger";

        if (DB::getDriverName() === 'pgsql') {
            $functionName = "increment_{$tableName}_management_version";
            DB::unprepared(<<<SQL
                CREATE OR REPLACE FUNCTION {$functionName}()
                RETURNS TRIGGER AS $$
                BEGIN
                    NEW.management_version := OLD.management_version + 1;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER {$triggerName}
                BEFORE UPDATE ON {$tableName}
                FOR EACH ROW
                EXECUTE FUNCTION {$functionName}();
                SQL);

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$triggerName}
                AFTER UPDATE ON {$tableName}
                FOR EACH ROW
                WHEN NEW.management_version <> OLD.management_version + 1
                BEGIN
                    UPDATE {$tableName}
                    SET management_version = OLD.management_version + 1
                    WHERE id = OLD.id;
                END;
                SQL);

            return;
        }

        throw new \RuntimeException('Management version triggers require PostgreSQL or SQLite.');
    }

    private function dropVersionTrigger(string $tableName): void
    {
        $triggerName = "{$tableName}_management_version_trigger";

        if (DB::getDriverName() === 'pgsql') {
            $functionName = "increment_{$tableName}_management_version";
            DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName} ON {$tableName}");
            DB::unprepared("DROP FUNCTION IF EXISTS {$functionName}()");

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName}");

            return;
        }

        throw new \RuntimeException('Management version triggers require PostgreSQL or SQLite.');
    }
};
