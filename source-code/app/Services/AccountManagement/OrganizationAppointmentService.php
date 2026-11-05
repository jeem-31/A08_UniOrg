<?php

namespace App\Services\AccountManagement;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Policies\OsasAccountPolicy;
use App\Services\OrganizationPermissionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Applies canonical organization appointments through the existing pivot.
 *
 * Adviser and officer appointments share one locked pipeline: the organization,
 * the selected account, the requested slot, and every conflicting active slot of
 * the same position family are locked in a deterministic order, then eligibility
 * and freshness are re-evaluated under those locks. A completed request changes
 * exactly one slot, promotes an eligible student only after every check passes,
 * and appends exactly one credential-free audit entry inside the transaction.
 * Only the position family differs: an adviser conflicts with the other adviser
 * slot, an officer with any other officer slot in the same organization.
 */
final readonly class OrganizationAppointmentService
{
    /** Operation names every appointment freshness snapshot is scoped to. */
    public const ADVISER_OPERATION = 'adviser.appointment';

    public const OFFICER_OPERATION = 'officer.appointment';

    private const ACTIVE_MEMBERSHIP = 'Active';

    /** Roles an appointable account may already hold. */
    private const APPOINTABLE_ROLES = ['admin', 'student'];

    /** Role an appointed account holds after a completed appointment. */
    private const APPOINTED_ROLE = 'admin';

    private const PROMOTABLE_ROLE = 'student';

    /** @var list<string> Portable duplicate-key states for the appointment indexes. */
    private const UNIQUE_VIOLATION_STATES = ['23000', '23505'];

    public function __construct(
        private OsasAccountPolicy $policy,
        private FreshnessVerifier $freshness,
        private AccountManagementAuditWriter $audits,
        private OrganizationPermissionService $permissions,
    ) {}

    public static function operationFor(AdviserPosition|OfficerPosition $position): string
    {
        return $position instanceof AdviserPosition ? self::ADVISER_OPERATION : self::OFFICER_OPERATION;
    }

    /**
     * The snapshot an appointment form is displayed with.
     *
     * The same builder runs again after the rows are locked, so any change to the
     * organization, the selected account, the requested slot, or a conflicting
     * slot makes the submitted snapshot stale.
     */
    public function currentFreshness(
        ActorAccountId $actor,
        OrganizationId $organization,
        ManagedAccountId $candidate,
        AdviserPosition|OfficerPosition $position,
    ): ?FreshnessSnapshot {
        $organizationRecord = Organization::query()->whereKey($organization->value)->first();
        $candidateRecord = User::query()->whereKey($candidate->value)->first();

        if ($organizationRecord === null || $candidateRecord === null) {
            return null;
        }

        $pivots = $this->relevantPivots(
            (int) $organizationRecord->getKey(),
            (int) $candidateRecord->getKey(),
            $position,
            false,
        );

        return $this->snapshot($actor->value, $organizationRecord, $candidateRecord, $position, $pivots);
    }

    /**
     * Creates or replaces exactly one Senior or Junior Adviser appointment.
     */
    public function appointAdviser(OrganizationAppointmentCommand $command): AccountManagementCommandResult
    {
        if (! $command->isAdviser()) {
            return $this->rejected(AccountManagementFailure::Invalid, [
                'position' => ['The submitted position is not an adviser position.'],
            ]);
        }

        return $this->appoint($command);
    }

    /**
     * Creates or replaces exactly one canonical Officer appointment.
     *
     * The shared pipeline supplies the locking, freshness, promotion, audit, and
     * cache rules; officer requests differ only in the conflict family, so the
     * selected account is rejected when it already holds any other active
     * officer position in the same organization, and only the submitted slot is
     * replaced. Every other assignment of the replaced and selected accounts,
     * including their adviser slots and assignments in other organizations, is
     * left untouched.
     */
    public function appointOfficer(OrganizationAppointmentCommand $command): AccountManagementCommandResult
    {
        if ($command->isAdviser()) {
            return $this->rejected(AccountManagementFailure::Invalid, [
                'position' => ['The submitted position is not an officer position.'],
            ]);
        }

        return $this->appoint($command);
    }

    /**
     * The shared locked appointment pipeline for every canonical position.
     */
    private function appoint(OrganizationAppointmentCommand $command): AccountManagementCommandResult
    {
        $actor = $this->authorizedActor($command->actor);

        if ($actor === null) {
            return $this->rejected(AccountManagementFailure::Unauthorized);
        }

        /** @var list<int> $invalidatedUserIds */
        $invalidatedUserIds = [];

        $result = $this->transactional(
            function () use ($command, $actor, &$invalidatedUserIds): AccountManagementCommandResult {
                return $this->appointUnderLocks($command, $actor, $invalidatedUserIds);
            },
        );

        if ($result->completed()) {
            // Committed state first, then cached organization permissions for the
            // selected and any replaced account.
            foreach ($invalidatedUserIds as $userId) {
                $this->permissions->invalidateCache($userId, $command->organization->value);
            }
        }

        return $result;
    }

    /**
     * @param  list<int>  $invalidatedUserIds
     */
    private function appointUnderLocks(
        OrganizationAppointmentCommand $command,
        User $actor,
        array &$invalidatedUserIds,
    ): AccountManagementCommandResult {
        $organization = Organization::query()
            ->whereKey($command->organization->value)
            ->lockForUpdate()
            ->first();

        if ($organization === null) {
            return $this->rejected(AccountManagementFailure::NotFound);
        }

        $candidate = User::query()->whereKey($command->candidate->value)->lockForUpdate()->first();

        if ($candidate === null) {
            return $this->rejected(AccountManagementFailure::NotFound);
        }

        $pivots = $this->relevantPivots(
            (int) $organization->getKey(),
            (int) $candidate->getKey(),
            $command->position,
            true,
        );

        $stale = $this->staleResult(
            $command->freshness,
            $this->snapshot($command->actor->value, $organization, $candidate, $command->position, $pivots),
        );

        if ($stale !== null) {
            return $stale;
        }

        $ineligible = $this->ineligibility($organization, $candidate, $command->position, $pivots);

        if ($ineligible !== null) {
            return $ineligible;
        }

        return $this->applyAppointment(
            $actor,
            $organization,
            $candidate,
            $command->position,
            $pivots['slot'],
            $invalidatedUserIds,
        );
    }

    /**
     * @param  array{slot: ?OrganizationUser, conflicts: list<OrganizationUser>}  $pivots
     */
    private function ineligibility(
        Organization $organization,
        User $candidate,
        AdviserPosition|OfficerPosition $position,
        array $pivots,
    ): ?RejectedAccountManagementCommand {
        if (! $organization->canBeUsed()) {
            return $this->rejected(AccountManagementFailure::Ineligible, [
                'organization' => ['The organization is not eligible for appointments.'],
            ]);
        }

        if (! $candidate->is_active) {
            return $this->rejected(AccountManagementFailure::Ineligible, [
                'candidate' => ['The selected account is inactive.'],
            ]);
        }

        if (! in_array((string) $candidate->role, self::APPOINTABLE_ROLES, true)) {
            return $this->rejected(AccountManagementFailure::Ineligible, [
                'candidate' => ['The selected account holds a system role that cannot receive this appointment.'],
            ]);
        }

        if ($pivots['conflicts'] !== []) {
            return $this->rejected(AccountManagementFailure::Conflict, [
                'candidate' => [$position instanceof AdviserPosition
                    ? 'The selected account already holds the other adviser position in this organization.'
                    : 'The selected account already holds another officer position in this organization.'],
            ]);
        }

        if ($pivots['slot'] !== null && (int) $pivots['slot']->user_id === (int) $candidate->getKey()) {
            return $this->rejected(AccountManagementFailure::Invalid, [
                'candidate' => ['The selected account already holds this position.'],
            ]);
        }

        return null;
    }

    /**
     * @param  list<int>  $invalidatedUserIds
     */
    private function applyAppointment(
        User $actor,
        Organization $organization,
        User $candidate,
        AdviserPosition|OfficerPosition $position,
        ?OrganizationUser $slot,
        array &$invalidatedUserIds,
    ): AccountManagementCommandResult {
        $formerAssignee = $slot === null
            ? null
            : User::query()->whereKey((int) $slot->user_id)->first();

        // Only the requested slot is retired; every other assignment of the
        // replaced account and of the selected account is left untouched.
        if ($slot !== null && (int) $slot->delete() !== 1) {
            throw new RuntimeException('The replaced appointment could not be retired.');
        }

        $previousRole = (string) $candidate->role;

        if ($previousRole === self::PROMOTABLE_ROLE) {
            $this->promote($candidate);
        }

        OrganizationUser::createAssignment($candidate, $organization, $position->role(), self::ACTIVE_MEMBERSHIP);

        $this->audits->record($this->completedAppointment(
            $actor,
            $organization,
            $position,
            $formerAssignee,
            $candidate,
            $previousRole,
        ));

        $invalidatedUserIds = array_values(array_unique(array_filter([
            (int) $candidate->getKey(),
            $formerAssignee === null ? null : (int) $formerAssignee->getKey(),
        ], static fn (?int $id): bool => $id !== null)));

        return new CompletedAccountManagementCommand(
            $position instanceof AdviserPosition
                ? AccountManagementAction::AdviserAppointed
                : AccountManagementAction::OfficerAppointed,
            new ManagedAccountId((int) $candidate->getKey()),
            new OrganizationId((int) $organization->getKey()),
            $position,
        );
    }

    private function completedAppointment(
        User $actor,
        Organization $organization,
        AdviserPosition|OfficerPosition $position,
        ?User $formerAssignee,
        User $candidate,
        string $previousRole,
    ): CompletedAction {
        $actorIdentity = AuditIdentity::fromUser($actor);
        $organizationIdentity = AuditIdentity::fromOrganization($organization);
        $former = $formerAssignee === null ? null : AuditIdentity::fromUser($formerAssignee);
        $selected = AuditIdentity::fromUser($candidate);
        $resultingRole = (string) $candidate->role;

        return match (true) {
            $position instanceof AdviserPosition => CompletedAction::adviserAppointed(
                $actorIdentity,
                $organizationIdentity,
                $position,
                $former,
                $selected,
                $previousRole,
                $resultingRole,
            ),
            default => CompletedAction::officerAppointed(
                $actorIdentity,
                $organizationIdentity,
                $position,
                $former,
                $selected,
                $previousRole,
                $resultingRole,
            ),
        };
    }

    /**
     * Locks and partitions the requested slot and every conflicting active slot.
     *
     * @return array{slot: ?OrganizationUser, conflicts: list<OrganizationUser>}
     */
    private function relevantPivots(
        int $organizationId,
        int $candidateId,
        AdviserPosition|OfficerPosition $position,
        bool $lock,
    ): array {
        $conflictingRoles = $this->conflictingRoles($position);

        $query = OrganizationUser::query()
            ->where('organization_id', $organizationId)
            ->where('membership_status', self::ACTIVE_MEMBERSHIP)
            ->where(function ($rows) use ($position, $candidateId, $conflictingRoles): void {
                $rows->where('role', $position->role())
                    ->orWhere(function ($conflicts) use ($candidateId, $conflictingRoles): void {
                        $conflicts->where('user_id', $candidateId)
                            ->whereIn('role', $conflictingRoles);
                    });
            })
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $slot = null;
        $conflicts = [];

        foreach ($query->get() as $pivot) {
            if ((string) $pivot->role === $position->role()) {
                if ($slot !== null) {
                    // The database slot invariant is broken; change nothing.
                    throw new RuntimeException('The requested appointment slot holds more than one active record.');
                }

                $slot = $pivot;

                continue;
            }

            $conflicts[] = $pivot;
        }

        return ['slot' => $slot, 'conflicts' => $conflicts];
    }

    /**
     * The active positions of the same family a selected account may not hold.
     *
     * @return list<string>
     */
    private function conflictingRoles(AdviserPosition|OfficerPosition $position): array
    {
        $family = $position instanceof AdviserPosition
            ? OrganizationPositionRegistry::adviserRoles()
            : OrganizationPositionRegistry::officerRoles();

        return array_values(array_filter(
            $family,
            static fn (string $role): bool => $role !== $position->role(),
        ));
    }

    /**
     * @param  array{slot: ?OrganizationUser, conflicts: list<OrganizationUser>}  $pivots
     */
    private function snapshot(
        int $actorId,
        Organization $organization,
        User $candidate,
        AdviserPosition|OfficerPosition $position,
        array $pivots,
    ): FreshnessSnapshot {
        $organizationId = (int) $organization->getKey();
        $slot = $pivots['slot'];

        return new FreshnessSnapshot(
            operation: self::operationFor($position),
            actorId: $actorId,
            targetId: (int) $candidate->getKey(),
            users: [FreshnessRecord::user((int) $candidate->getKey(), (int) $candidate->management_version)],
            organization: FreshnessRecord::organization($organizationId, (int) $organization->management_version),
            slot: $slot === null
                ? FreshnessSlot::empty($organizationId, $position->role())
                : FreshnessSlot::occupied($organizationId, $position->role(), $this->pivotRecord($slot)),
            conflictingPivots: array_map(
                fn (OrganizationUser $pivot): FreshnessRecord => $this->pivotRecord($pivot),
                $pivots['conflicts'],
            ),
        );
    }

    private function pivotRecord(OrganizationUser $pivot): FreshnessRecord
    {
        return FreshnessRecord::pivot(
            (int) $pivot->getKey(),
            (int) $pivot->management_version,
            (int) $pivot->organization_id,
            (int) $pivot->user_id,
        );
    }

    /**
     * Promotes an eligible student with the observed version in the WHERE clause
     * so a bypassed lock cannot silently overwrite newer state.
     */
    private function promote(User $candidate): void
    {
        $expectedVersion = (int) $candidate->management_version;

        $applied = User::query()
            ->whereKey($candidate->getKey())
            ->where('management_version', $expectedVersion)
            ->update(['role' => self::APPOINTED_ROLE]);

        if ($applied !== 1) {
            throw new RuntimeException('The selected account changed while the appointment was being applied.');
        }

        $candidate->refresh();
    }

    private function authorizedActor(ActorAccountId $actor): ?User
    {
        $current = User::query()->find($actor->value);

        return $current !== null && $this->policy->manage($current) ? $current : null;
    }

    private function staleResult(
        FreshnessSnapshot $submitted,
        FreshnessSnapshot $current,
    ): ?RejectedAccountManagementCommand {
        $result = $this->freshness->compare($submitted, $current);

        if ($result->accepted()) {
            return null;
        }

        return new RejectedAccountManagementCommand(
            AccountManagementFailure::Stale,
            $result instanceof StaleSubmission ? $result->reload : new ReloadAffordance,
        );
    }

    /**
     * @param  callable(): AccountManagementCommandResult  $work
     */
    private function transactional(callable $work): AccountManagementCommandResult
    {
        try {
            return DB::transaction($work);
        } catch (QueryException $failure) {
            // Pivot, role, version, and audit changes roll back together.
            return $this->rejected(
                in_array((string) $failure->getCode(), self::UNIQUE_VIOLATION_STATES, true)
                    ? AccountManagementFailure::Conflict
                    : AccountManagementFailure::Persistence,
            );
        } catch (Throwable) {
            return $this->rejected(AccountManagementFailure::Persistence);
        }
    }

    /** @param array<string, list<string>> $fieldErrors */
    private function rejected(
        AccountManagementFailure $failure,
        array $fieldErrors = [],
    ): RejectedAccountManagementCommand {
        return new RejectedAccountManagementCommand($failure, null, $fieldErrors);
    }
}
