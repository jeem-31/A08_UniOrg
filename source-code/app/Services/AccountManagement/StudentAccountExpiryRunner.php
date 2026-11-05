<?php

namespace App\Services\AccountManagement;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deactivates student accounts whose fixed validity window (expiry plus the
 * two-month grace period) has fully passed.
 *
 * The runner only ever flips an active account to inactive and records an
 * `account.expired` audit entry; it never reactivates, so manual lifecycle
 * control and approval-driven activation remain untouched. The state change
 * and its audit entry commit together inside one transaction, matching every
 * other managed-account action.
 */
final class StudentAccountExpiryRunner
{
    public function __construct(private AccountManagementAuditWriter $audits) {}

    /** @return array{deactivated: int} */
    public function run(): array
    {
        $cutoff = now(config('app.timezone'))->subMonths(StudentAccountValidity::GRACE_MONTHS);

        $expired = User::query()
            ->where('role', 'student')
            ->whereRaw('CAST(users.is_active AS INTEGER) = ?', [1])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $cutoff)
            ->get();

        $deactivated = 0;

        DB::transaction(function () use ($expired, &$deactivated): void {
            foreach ($expired as $student) {
                $student->is_active = false;
                $student->save();

                $this->audits->record(CompletedAction::accountExpired(
                    AuditIdentity::fromUser($student),
                ));

                $deactivated++;
            }
        });

        return ['deactivated' => $deactivated];
    }
}
