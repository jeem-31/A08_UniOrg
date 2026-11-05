<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove the stale applicant created as "Assistant Secretary" from the pending
 * application after the position was removed. The applicant was re-rolered to
 * "Secretary" by the position-removal migration, which would leave the pending
 * application with two Secretary officers (the original Secretary + the former
 * Assistant Secretary).
 *
 * If the account has no other persisted references it is deleted outright;
 * otherwise it is unlinked from the application (apply_org_id / temp_position)
 * so no unrelated records are affected.
 */
return new class extends Migration
{
    private const APPLICANT_ID = 83;

    private const APPLICANT_EMAIL = 'asd@asd';

    /** @var list<array{0:string,1:string}> [table, column] */
    private const REFERENCE_COLUMNS = [
        ['configuration_tokens', 'user_id'],
        ['organization_user', 'user_id'],
        ['notifications', 'notifiable_id'],
        ['documents', 'user_id'],
        ['documents', 'uploaded_by'],
        ['events', 'user_id'],
        ['student_payments', 'user_id'],
        ['event_registrations', 'user_id'],
        ['shared_documents', 'student_id'],
        ['admin_chats', 'user_id'],
        ['osas_chats', 'sender_id'],
        ['candidates', 'user_id'],
        ['votes', 'user_id'],
        ['system_requests', 'reviewed_by'],
    ];

    public function up(): void
    {
        $applicant = DB::table('users')
            ->where('id', self::APPLICANT_ID)
            ->where('email', self::APPLICANT_EMAIL)
            ->first();

        if ($applicant === null) {
            return;
        }

        $hasReferences = $this->hasReferences();

        if ($hasReferences) {
            DB::table('users')
                ->where('id', self::APPLICANT_ID)
                ->update(['apply_org_id' => null, 'temp_position' => null, 'updated_at' => now()]);

            return;
        }

        DB::table('users')->where('id', self::APPLICANT_ID)->delete();
    }

    public function down(): void
    {
        // Destructive removal of the applicant cannot be restored from here.
    }

    private function hasReferences(): bool
    {
        foreach (self::REFERENCE_COLUMNS as [$table, $column]) {
            if (! DB::getSchemaBuilder()->hasColumn($table, $column)) {
                continue;
            }

            if (DB::table($table)->where($column, self::APPLICANT_ID)->exists()) {
                return true;
            }
        }

        return false;
    }
};
