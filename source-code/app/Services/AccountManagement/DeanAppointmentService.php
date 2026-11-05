<?php

namespace App\Services\AccountManagement;

use App\Models\User;
use App\Policies\OsasAccountPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Applies the one-active-Dean-per-college appointment.
 *
 * Every request re-authorizes the actor, locks the candidate together with the
 * current active Dean row of the candidate's department in ascending identifier
 * order, re-checks eligibility and freshness under those locks, and then either
 * completes the whole transition with exactly one audit entry or changes nothing
 * at all. Organization assignments are never read for update or written here,
 * so both accounts keep their pivots byte-for-byte.
 */
final readonly class DeanAppointmentService
{
    /** Operation name every Dean freshness snapshot must be scoped to. */
    public const OPERATION = 'dean.appointment';

    private const DEAN_ROLE = 'super_admin';

    /** Role a deactivated former Dean receives (Requirement 5.4). */
    private const DEACTIVATED_FORMER_DEAN_ROLE = 'student';

    /** @var list<string> Portable duplicate-key states for the active-Dean index. */
    private const UNIQUE_VIOLATION_STATES = ['23000', '23505'];

    public function __construct(
        private OsasAccountPolicy $policy,
        private FreshnessVerifier $freshness,
        private DestructiveConfirmationGuard $confirmations,
        private AccountManagementAuditWriter $audits,
    ) {}

    /**
     * The snapshot a Dean appointment form is displayed with and re-checked against.
     */
    public static function appointmentFreshness(
        ActorAccountId $actor,
        ManagedAccountId $candidate,
        int $candidateVersion,
        ?int $formerDeanId = null,
        ?int $formerDeanVersion = null,
    ): FreshnessSnapshot {
        $users = [FreshnessRecord::user($candidate->value, $candidateVersion)];

        if ($formerDeanId !== null && $formerDeanVersion !== null && $formerDeanId !== $candidate->value) {
            $users[] = FreshnessRecord::user($formerDeanId, $formerDeanVersion);
        }

        return new FreshnessSnapshot(
            operation: self::OPERATION,
            actorId: $actor->value,
            targetId: $candidate->value,
            users: $users,
        );
    }

    public function appoint(DeanAppointmentCommand $command): AccountManagementCommandResult
    {
        $actor = $this->authorizedActor($command->actor);

        if ($actor === null) {
            return $this->rejected(AccountManagementFailure::Unauthorized);
        }

        if ($command->candidate->value === $command->actor->value) {
            return $this->rejected(AccountManagementFailure::SelfAction);
        }

        return $this->transactional(
            fn (): AccountManagementCommandResult => $this->appointUnderLocks($command, $actor),
        );
    }

    private function appointUnderLocks(DeanAppointmentCommand $command, User $actor): AccountManagementCommandResult
    {
        $locked = $this->lockCandidateAndDepartmentDean($command->candidate->value);

        if ($locked === null) {
            return $this->rejected(AccountManagementFailure::Conflict);
        }

        [$candidate, $currentDeans] = $locked;

        if ($candidate === null) {
            return $this->rejected(AccountManagementFailure::NotFound);
        }

        if (! $candidate->is_active) {
            return $this->rejected(AccountManagementFailure::Ineligible);
        }

        if (count($currentDeans) > 1) {
            return $this->rejected(AccountManagementFailure::Conflict);
        }

        $formerDean = $currentDeans[0] ?? null;

        if ($formerDean !== null && (int) $formerDean->getKey() === (int) $candidate->getKey()) {
            return $this->rejected(AccountManagementFailure::Invalid, [
                'candidate' => ['The selected account already holds the current Dean appointment.'],
            ]);
        }

        $stale = $this->staleResult(
            $command->freshness,
            self::appointmentFreshness(
                $command->actor,
                $command->candidate,
                (int) $candidate->management_version,
                $formerDean === null ? null : (int) $formerDean->getKey(),
                $formerDean === null ? null : (int) $formerDean->management_version,
            ),
        );

        if ($stale !== null) {
            return $stale;
        }

        if ($formerDean === null) {
            return $command->formerDeanOutcome === null
                ? $this->assignInitialDean($actor, $candidate)
                : $this->rejected(AccountManagementFailure::Invalid, [
                    'former_dean_outcome' => ['A former Dean outcome applies only when a current Dean is replaced.'],
                ]);
        }

        return $command->formerDeanOutcome === null
            ? $this->rejected(AccountManagementFailure::Invalid, [
                'former_dean_outcome' => ['Select what happens to the current Dean before confirming a replacement.'],
            ])
            : $this->replaceDean($actor, $candidate, $formerDean, $command->formerDeanOutcome, $command->confirmation);
    }

    private function assignInitialDean(User $actor, User $candidate): AccountManagementCommandResult
    {
        $previousRole = (string) $candidate->role;

        $this->applyManagedChanges($candidate, ['role' => self::DEAN_ROLE]);

        $this->audits->record(CompletedAction::deanAssigned(
            AuditIdentity::fromUser($actor),
            AuditIdentity::fromUser($candidate),
            $previousRole,
        ));

        return new CompletedAccountManagementCommand(
            AccountManagementAction::DeanAssigned,
            new ManagedAccountId((int) $candidate->getKey()),
        );
    }

    private function replaceDean(
        User $actor,
        User $candidate,
        User $formerDean,
        NonDeanOutcome $outcome,
        ?DestructiveConfirmationToken $confirmation,
    ): AccountManagementCommandResult {
        $expected = DestructiveConfirmation::forDeanReplacement(
            (int) $actor->getKey(),
            (int) $candidate->getKey(),
            (int) $candidate->management_version,
            (int) $formerDean->getKey(),
            (int) $formerDean->management_version,
            $outcome,
        );

        $completed = null;

        $verification = $this->confirmations->run(
            $confirmation?->value(),
            $expected,
            function () use ($actor, $candidate, $formerDean, $outcome, &$completed): void {
                $incomingPreviousRole = (string) $candidate->role;

                // The former Dean leaves the department's active-Dean slot first
                // so the promotion below can never collide with the invariant.
                $this->applyManagedChanges($formerDean, [
                    'role' => $outcome->role ?? self::DEACTIVATED_FORMER_DEAN_ROLE,
                    'is_active' => false,
                ]);
                $this->applyManagedChanges($candidate, ['role' => self::DEAN_ROLE]);

                $this->audits->record(CompletedAction::deanReplaced(
                    AuditIdentity::fromUser($actor),
                    AuditIdentity::fromUser($candidate),
                    $incomingPreviousRole,
                    AuditIdentity::fromUser($formerDean),
                    $outcome,
                ));

                $completed = new CompletedAccountManagementCommand(
                    AccountManagementAction::DeanReplaced,
                    new ManagedAccountId((int) $candidate->getKey()),
                );
            },
        );

        if (! $verification->accepted() || $completed === null) {
            return $this->rejected(AccountManagementFailure::Confirmation);
        }

        return $completed;
    }

    /**
     * Locks the candidate and the active Dean of the candidate's department by
     * ascending identifier. A Dean candidate keeps its department for its whole
     * lifetime (it is only set at creation), so probing it before locking is
     * safe; the per-department unique index remains the concurrency backstop.
     *
     * @return array{0: ?User, 1: list<User>}|null Null when the active-Dean set
     *                                             changed while locks were acquired.
     */
    private function lockCandidateAndDepartmentDean(int $candidateId): ?array
    {
        $departmentId = (int) User::query()
            ->whereKey($candidateId)
            ->value('department_id');

        $identifiers = $this->activeDeanIds($departmentId);
        $identifiers[] = $candidateId;
        $identifiers = array_values(array_unique($identifiers));
        sort($identifiers);

        $locked = User::query()
            ->whereIn('id', $identifiers)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(static fn (User $user): int => (int) $user->getKey());

        $deans = [];

        foreach ($this->activeDeanIds($departmentId) as $deanId) {
            $dean = $locked->get($deanId);

            if ($dean === null) {
                return null;
            }

            $deans[] = $dean;
        }

        return [$locked->get($candidateId), $deans];
    }

    /** @return list<int> */
    private function activeDeanIds(?int $departmentId): array
    {
        return User::query()
            ->where('role', self::DEAN_ROLE)
            ->whereRaw('CAST(users.is_active AS INTEGER) = 1')
            ->when(
                $departmentId === null || $departmentId <= 0,
                static fn ($query) => $query->whereNull('department_id'),
                static fn ($query) => $query->where('department_id', $departmentId),
            )
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
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
            // Every user, role, state, and audit change rolled back together.
            return $this->rejected(
                in_array((string) $failure->getCode(), self::UNIQUE_VIOLATION_STATES, true)
                    ? AccountManagementFailure::Conflict
                    : AccountManagementFailure::Persistence,
            );
        } catch (Throwable) {
            return $this->rejected(AccountManagementFailure::Persistence);
        }
    }

    /**
     * Applies allow-listed managed columns with the observed version in the
     * WHERE clause so a bypassed lock cannot silently overwrite newer state.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function applyManagedChanges(User $user, array $attributes): void
    {
        $expectedVersion = (int) $user->management_version;

        foreach ($attributes as $attribute => $value) {
            $user->{$attribute} = $value;
        }

        $changes = $user->getDirty();

        if ($changes === []) {
            return;
        }

        $applied = User::query()
            ->whereKey($user->getKey())
            ->where('management_version', $expectedVersion)
            ->update($changes);

        if ($applied !== 1) {
            throw new RuntimeException('A managed account changed while the Dean appointment was being applied.');
        }

        $user->refresh();
    }

    /** @param array<string, list<string>> $fieldErrors */
    private function rejected(
        AccountManagementFailure $failure,
        array $fieldErrors = [],
    ): RejectedAccountManagementCommand {
        return new RejectedAccountManagementCommand($failure, null, $fieldErrors);
    }
}
