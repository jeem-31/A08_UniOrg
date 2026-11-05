<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow an organization member's membership to be set to 'Inactive'.
 *
 * The adviser's Settings > Manage Accounts tab deactivates a member's account
 * by flipping `users.is_active` and their `organization_user.membership_status`
 * to 'Inactive' (keeping their role for a reversible restore). The original
 * column only allowed 'Active' and 'Pending', so this migration extends the
 * allowed values.
 *
 * PostgreSQL may store the column as a native enum type (older Laravel) or as a
 * varchar + CHECK constraint (current grammar). Both shapes are handled. On
 * SQLite/MySQL the check constraint is rebuilt to include the new value.
 *
 * NOTE: this migration intentionally runs BEFORE the 07-25 migrations that add
 * the partial unique indexes and the `management_version` trigger on SQLite.
 * SQLite implements `change()` by rebuilding the whole table, which drops those
 * triggers and strips the WHERE clause from partial indexes — running first
 * ensures they are recreated fresh on the already-rebuilt table.
 */
return new class extends Migration
{
    private const ENUM_TYPE = 'organization_user_membership_status';

    private const ALLOWED_WITH_INACTIVE = ['Active', 'Pending', 'Inactive'];

    private const ALLOWED_ORIGINAL = ['Active', 'Pending'];

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $enumExists = DB::select(
                'SELECT EXISTS (SELECT 1 FROM pg_type WHERE typname = ?) AS exists',
                [self::ENUM_TYPE],
            )[0]->exists;

            if ($enumExists) {
                DB::statement('ALTER TYPE '.self::ENUM_TYPE." ADD VALUE IF NOT EXISTS 'Inactive'");
                return;
            }

            DB::statement('ALTER TABLE organization_user DROP CONSTRAINT IF EXISTS organization_user_membership_status_check');
            DB::statement(
                "ALTER TABLE organization_user ADD CONSTRAINT organization_user_membership_status_check "
                .'CHECK (membership_status IN ('.implode(', ', array_map(
                    static fn (string $value): string => "'{$value}'",
                    self::ALLOWED_WITH_INACTIVE,
                )).'))'
            );
            return;
        }

        // SQLite (tests) and MySQL: rebuild the check constraint to include 'Inactive'.
        Schema::table('organization_user', function (Blueprint $table) {
            $table->enum('membership_status', self::ALLOWED_WITH_INACTIVE)->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQL does not support removing enum values without recreating
            // the type; leaving the extra value in place is harmless.
            return;
        }

        Schema::table('organization_user', function (Blueprint $table) {
            $table->enum('membership_status', self::ALLOWED_ORIGINAL)->change();
        });
    }
};
