<?php

namespace App\Services\AccountManagement;

use App\Models\AccountManagementAudit;
use App\Models\OrganizationUser;
use App\Models\User;
use Throwable;

/**
 * Read-only detail loader for exactly one Managed_Account.
 *
 * Every read is scoped by the requested target ID. A missing target, a failed
 * audit retrieval, and a failure to prove target isolation are distinct typed
 * outcomes; no outcome substitutes another account, organization, or assignment.
 */
final class ManagedAccountDetailsQuery
{
    public function findDetails(int $id, DirectoryContext $context): AccountDetailsResult
    {
        if ($id < 1) {
            return AccountDetailsNotFound::for($context);
        }

        try {
            $user = User::query()->whereKey($id)->first();
        } catch (Throwable) {
            return AccountDetailsIsolationFailure::for($context);
        }

        if ($user === null) {
            return AccountDetailsNotFound::for($context);
        }

        if ((int) $user->getKey() !== $id) {
            return AccountDetailsIsolationFailure::for($context);
        }

        try {
            $assignments = $this->assignments($id);
        } catch (Throwable) {
            return AccountDetailsIsolationFailure::for($context);
        }

        // A retrieval failure keeps the remaining details available (audit-only failure),
        // while an out-of-scope audit row is an isolation failure and fails closed.
        try {
            $auditEntries = $this->auditEntries($id);
        } catch (Throwable) {
            $auditEntries = null;
        }

        try {
            return ResolvedAccountDetails::forTarget($user, $assignments, $auditEntries, $context);
        } catch (Throwable) {
            return AccountDetailsIsolationFailure::for($context);
        }
    }

    /**
     * Current Organization_Assignments of the target only.
     *
     * @return list<ManagedAccountAssignmentSummary>
     */
    private function assignments(int $id): array
    {
        $assignments = OrganizationUser::query()
            ->where('user_id', $id)
            ->where('membership_status', 'Active')
            ->with('organization')
            ->orderBy('id')
            ->get();

        return $assignments
            ->map(static fn (OrganizationUser $assignment): ManagedAccountAssignmentSummary => ManagedAccountAssignmentSummary::fromAssignment($assignment, $id))
            ->values()
            ->all();
    }

    /**
     * At most the ten newest Audit_Entries recorded against the target.
     *
     * @return list<AuditEntryView>
     */
    private function auditEntries(int $id): array
    {
        $audits = AccountManagementAudit::query()
            ->where('affected_user_id', $id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(ResolvedAccountDetails::MAX_AUDIT_ENTRIES)
            ->get();

        return $audits
            ->map(static fn (AccountManagementAudit $audit): AuditEntryView => AuditEntryView::fromRecord($audit))
            ->values()
            ->all();
    }
}
