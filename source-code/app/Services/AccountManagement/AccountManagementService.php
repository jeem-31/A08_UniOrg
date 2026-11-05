<?php

namespace App\Services\AccountManagement;

use App\Models\Organization;
use App\Models\User;
use App\Policies\OsasAccountPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Throwable;

/**
 * Transactional managed-account creation, profile patching, ordinary non-Dean
 * role changes, activation-state changes, and controlled permanent deletion.
 *
 * Every command locks the rows it touches, re-checks freshness and eligibility
 * after locking, applies only its declared fields, and appends exactly one
 * credential-free audit record inside the same transaction. A rejected command
 * writes nothing at all.
 */
final readonly class AccountManagementService
{
    public const OPERATION_PROFILE_UPDATE = 'account.profile_update';

    public const OPERATION_ROLE_CHANGE = 'account.role_change';

    public const OPERATION_LIFECYCLE = 'account.lifecycle';

    public const OPERATION_DELETE = 'account.delete';

    private const DEAN_ROLE = 'super_admin';

    /** @var list<string> Portable duplicate-key states for the account identity indexes. */
    private const UNIQUE_VIOLATION_STATES = ['23000', '23505'];

    /** Presentation-safe reasons for a blocked deletion; no record details leak. */
    private const DELETION_REASONS = [
        AssociatedRecordInspection::ASSOCIATED_RECORDS => 'This account has related institutional records, so it can only be deactivated.',
        AssociatedRecordInspection::RETENTION_ACTIVE => 'This account cannot be permanently deleted yet. Deactivate it instead.',
        AssociatedRecordInspection::UNAVAILABLE => 'Deletion eligibility could not be confirmed, so the account was preserved.',
    ];

    public function __construct(
        private FreshnessVerifier $freshness,
        private AccountManagementAuditWriter $audits,
        private ManagedAccountValidator $validator,
        private OsasAccountPolicy $policy,
        private AssociatedRecordInspector $associations,
        private DestructiveConfirmationGuard $confirmations,
        private StudentAccountValidity $validity,
    ) {}

    /**
     * The snapshot a profile edit form is displayed with and re-checked against.
     */
    public static function profileFreshness(
        ActorAccountId $actor,
        ManagedAccountId $target,
        int $targetVersion,
    ): FreshnessSnapshot {
        return new FreshnessSnapshot(
            operation: self::OPERATION_PROFILE_UPDATE,
            actorId: $actor->value,
            targetId: $target->value,
            users: [FreshnessRecord::user($target->value, $targetVersion)],
        );
    }

    /**
     * The snapshot an ordinary role form is displayed with and re-checked against.
     */
    public static function roleFreshness(
        ActorAccountId $actor,
        ManagedAccountId $target,
        int $targetVersion,
    ): FreshnessSnapshot {
        return new FreshnessSnapshot(
            operation: self::OPERATION_ROLE_CHANGE,
            actorId: $actor->value,
            targetId: $target->value,
            users: [FreshnessRecord::user($target->value, $targetVersion)],
        );
    }

    /**
     * The snapshot an activation/deactivation control is displayed with and
     * re-checked against.
     */
    public static function lifecycleFreshness(
        ActorAccountId $actor,
        ManagedAccountId $target,
        int $targetVersion,
    ): FreshnessSnapshot {
        return new FreshnessSnapshot(
            operation: self::OPERATION_LIFECYCLE,
            actorId: $actor->value,
            targetId: $target->value,
            users: [FreshnessRecord::user($target->value, $targetVersion)],
        );
    }

    /**
     * The snapshot the deletion confirmation is issued with and re-checked against.
     */
    public static function deletionFreshness(
        ActorAccountId $actor,
        ManagedAccountId $target,
        int $targetVersion,
    ): FreshnessSnapshot {
        return new FreshnessSnapshot(
            operation: self::OPERATION_DELETE,
            actorId: $actor->value,
            targetId: $target->value,
            users: [FreshnessRecord::user($target->value, $targetVersion)],
        );
    }

    public function create(CreateManagedAccountCommand $command): AccountManagementCommandResult
    {
        $actor = $this->authorizedActor($command->actor);

        if ($actor === null) {
            return new RejectedAccountManagementCommand(AccountManagementFailure::Unauthorized);
        }

        $profile = $command->profile->values();
        $invalid = $this->validator->validateNewAccount($profile, $command->credential, $command->role->value);

        if ($invalid !== []) {
            return new RejectedAccountManagementCommand(AccountManagementFailure::Invalid, null, $invalid);
        }

        return $this->transactional(function () use ($actor, $command, $profile): AccountManagementCommandResult {
            $duplicates = $this->validator->duplicateIdentityMessages($profile, null);

            if ($duplicates !== []) {
                return new RejectedAccountManagementCommand(AccountManagementFailure::Conflict, null, $duplicates);
            }

            $account = new User;
            $account->fill($profile);
            $account->role = $command->role->value;
            $account->is_active = true;
            $account->password = Hash::make($command->credential->plainText());

            if ($command->role === AssignableNonDeanRole::Student) {
                $periodStart = Organization::currentAccreditationPeriod()?->started_at;
                $account->expires_at = $this->validity->expiresAtFor(
                    $profile['year_level'] ?? null,
                    null,
                    $periodStart,
                );
            }

            $account->save();

            $this->audits->record(CompletedAction::accountCreated(
                AuditIdentity::fromUser($actor),
                AuditIdentity::fromUser($account),
                $profile,
                $command->role,
            ));

            return new CompletedAccountManagementCommand(
                AccountManagementAction::AccountCreated,
                new ManagedAccountId((int) $account->getKey()),
            );
        });
    }

    public function updateProfile(UpdateManagedAccountProfileCommand $command): AccountManagementCommandResult
    {
        $actor = $this->authorizedActor($command->actor);

        if ($actor === null) {
            return new RejectedAccountManagementCommand(AccountManagementFailure::Unauthorized);
        }

        $profile = $command->profile->values();

        return $this->transactional(function () use ($actor, $command, $profile): AccountManagementCommandResult {
            $target = $this->lockedAccount($command->target);

            if ($target === null) {
                return new RejectedAccountManagementCommand(AccountManagementFailure::NotFound);
            }

            $stale = $this->staleResult($command->freshness, self::profileFreshness(
                $command->actor,
                $command->target,
                (int) $target->management_version,
            ));

            if ($stale !== null) {
                return $stale;
            }

            $invalid = $this->validator->validateProfilePatch($profile);

            if ($invalid !== []) {
                return new RejectedAccountManagementCommand(AccountManagementFailure::Invalid, null, $invalid);
            }

            $duplicates = $this->validator->duplicateIdentityMessages($profile, (int) $target->getKey());

            if ($duplicates !== []) {
                return new RejectedAccountManagementCommand(AccountManagementFailure::Conflict, null, $duplicates);
            }

            $before = [];

            foreach (array_keys($profile) as $field) {
                $before[$field] = $target->getAttribute($field);
            }

            $target->fill($profile);

            if ($target->isDirty()) {
                $target->save();

                $this->audits->record(CompletedAction::profileUpdated(
                    AuditIdentity::fromUser($actor),
                    AuditIdentity::fromUser($target),
                    $before,
                    $profile,
                ));
            }

            return new CompletedAccountManagementCommand(
                AccountManagementAction::ProfileUpdated,
                $command->target,
            );
        });
    }

    public function changeNonDeanRole(ChangeNonDeanRoleCommand $command): AccountManagementCommandResult
    {
        $actor = $this->authorizedActor($command->actor);

        if ($actor === null) {
            return new RejectedAccountManagementCommand(AccountManagementFailure::Unauthorized);
        }

        return $this->transactional(function () use ($actor, $command): AccountManagementCommandResult {
            $target = $this->lockedAccount($command->target);

            if ($target === null) {
                return new RejectedAccountManagementCommand(AccountManagementFailure::NotFound);
            }

            $stale = $this->staleResult($command->freshness, self::roleFreshness(
                $command->actor,
                $command->target,
                (int) $target->management_version,
            ));

            if ($stale !== null) {
                return $stale;
            }

            $previousRole = (string) $target->role;

            // An ordinary edit never changes the current Dean appointment.
            if ($previousRole === self::DEAN_ROLE) {
                return new RejectedAccountManagementCommand(AccountManagementFailure::Ineligible, null, [
                    'role' => ['The current Dean role can only change through Dean appointment management.'],
                ]);
            }

            if ($previousRole !== $command->role->value) {
                $target->role = $command->role->value;
                $target->save();

                $this->audits->record(CompletedAction::roleChanged(
                    AuditIdentity::fromUser($actor),
                    AuditIdentity::fromUser($target),
                    $previousRole,
                    $command->role,
                ));
            }

            return new CompletedAccountManagementCommand(
                AccountManagementAction::RoleChanged,
                $command->target,
            );
        });
    }

    /**
     * Deactivates or reactivates one other account, changing only `is_active`.
     *
     * Role and organization assignments are never read for update or written
     * here, so a reactivated account keeps its previous access exactly.
     */
    public function changeActivation(ChangeAccountLifecycleCommand $command): AccountManagementCommandResult
    {
        $actor = $this->authorizedActor($command->actor);

        if ($actor === null) {
            return new RejectedAccountManagementCommand(AccountManagementFailure::Unauthorized);
        }

        // Defense in depth: the command type already forbids a self-target.
        if ($command->actor->value === $command->target->value) {
            return new RejectedAccountManagementCommand(AccountManagementFailure::SelfAction);
        }

        return $this->transactional(function () use ($command): AccountManagementCommandResult {
            [$actor, $target] = $this->lockedActorAndTarget($command->actor, $command->target);

            if ($actor === null) {
                return new RejectedAccountManagementCommand(AccountManagementFailure::Unauthorized);
            }

            if ($target === null) {
                return new RejectedAccountManagementCommand(AccountManagementFailure::NotFound);
            }

            $stale = $this->staleResult($command->freshness, self::lifecycleFreshness(
                $command->actor,
                $command->target,
                (int) $target->management_version,
            ));

            if ($stale !== null) {
                return $stale;
            }

            $requestedActive = $command->action === AccountLifecycleAction::Activate;

            // A student whose fixed validity window (expiry plus grace) has fully
            // closed cannot be manually reactivated: the login guard still blocks
            // them and the daily expiry job would deactivate them again. Only a
            // renewed/updated expiry can restore their access.
            if ($requestedActive
                && $target->role === 'student'
                && $target->expires_at !== null
                && $this->validity->validityFor($target->expires_at) === ValidityState::Expired
            ) {
                return new RejectedAccountManagementCommand(AccountManagementFailure::Ineligible, null, [
                    'account' => ['This account has already expired and cannot be reactivated. Update its validity instead.'],
                ]);
            }

            if ((bool) $target->is_active !== $requestedActive) {
                $this->applyManagedChanges($target, ['is_active' => $requestedActive]);

                $this->audits->record($requestedActive
                    ? CompletedAction::accountReactivated(
                        AuditIdentity::fromUser($actor),
                        AuditIdentity::fromUser($target),
                    )
                    : CompletedAction::accountDeactivated(
                        AuditIdentity::fromUser($actor),
                        AuditIdentity::fromUser($target),
                    ));
            }

            return new CompletedAccountManagementCommand(
                $requestedActive
                    ? AccountManagementAction::AccountReactivated
                    : AccountManagementAction::AccountDeactivated,
                $command->target,
            );
        });
    }

    /**
     * Permanently deletes one other account only when it has no associated
     * record or deletion restriction and its bound confirmation is valid.
     */
    public function delete(DeleteManagedAccountCommand $command): AccountManagementCommandResult
    {
        $actor = $this->authorizedActor($command->actor);

        if ($actor === null) {
            return new RejectedAccountManagementCommand(AccountManagementFailure::Unauthorized);
        }

        // Defense in depth: the command type already forbids a self-target.
        if ($command->actor->value === $command->target->value) {
            return new RejectedAccountManagementCommand(AccountManagementFailure::SelfAction);
        }

        return $this->transactional(function () use ($command): AccountManagementCommandResult {
            [$actor, $target] = $this->lockedActorAndTarget($command->actor, $command->target);

            if ($actor === null) {
                return new RejectedAccountManagementCommand(AccountManagementFailure::Unauthorized);
            }

            if ($target === null) {
                return new RejectedAccountManagementCommand(AccountManagementFailure::NotFound);
            }

            $stale = $this->staleResult($command->freshness, self::deletionFreshness(
                $command->actor,
                $command->target,
                (int) $target->management_version,
            ));

            if ($stale !== null) {
                return $stale;
            }

            $inspection = $this->associations->inspect($target);

            if (! $inspection->canBeDeleted()) {
                return new RejectedAccountManagementCommand(AccountManagementFailure::Ineligible, null, [
                    'account' => [
                        self::DELETION_REASONS[$inspection->reasonCode()]
                            ?? self::DELETION_REASONS[AssociatedRecordInspection::UNAVAILABLE],
                    ],
                ]);
            }

            $completed = null;

            $verification = $this->confirmations->run(
                $command->confirmation->value(),
                DestructiveConfirmation::forAccountDeletion(
                    (int) $actor->getKey(),
                    (int) $target->getKey(),
                    (int) $target->management_version,
                ),
                function () use ($actor, $target, $command, &$completed): void {
                    // The redacted snapshot is appended before removal so the
                    // audit entry still identifies the account afterwards.
                    $this->audits->record(CompletedAction::accountDeleted(
                        AuditIdentity::fromUser($actor),
                        AuditIdentity::fromUser($target),
                    ));

                    $this->removeAccount($target);

                    $completed = new CompletedAccountManagementCommand(
                        AccountManagementAction::AccountDeleted,
                        $command->target,
                    );
                },
            );

            if (! $verification->accepted() || $completed === null) {
                return new RejectedAccountManagementCommand(AccountManagementFailure::Confirmation);
            }

            return $completed;
        });
    }

    private function authorizedActor(ActorAccountId $actor): ?User
    {
        $current = User::query()->find($actor->value);

        return $current !== null && $this->policy->manage($current) ? $current : null;
    }

    /**
     * Locks the acting and target rows together in ascending identifier order
     * so concurrent lifecycle commands cannot deadlock or observe stale state.
     *
     * @return array{0: ?User, 1: ?User} The still-authorized actor and the target.
     */
    private function lockedActorAndTarget(ActorAccountId $actor, ManagedAccountId $target): array
    {
        $identifiers = array_values(array_unique([$actor->value, $target->value]));
        sort($identifiers);

        $locked = User::query()
            ->whereIn('id', $identifiers)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(static fn (User $user): int => (int) $user->getKey());

        $lockedActor = $locked->get($actor->value);

        return [
            $lockedActor !== null && $this->policy->manage($lockedActor) ? $lockedActor : null,
            $locked->get($target->value),
        ];
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
            throw new RuntimeException('A managed account changed while the lifecycle action was being applied.');
        }

        $user->refresh();
    }

    /** Removes the account only while it still matches the observed version. */
    private function removeAccount(User $user): void
    {
        $removed = User::query()
            ->whereKey($user->getKey())
            ->where('management_version', (int) $user->management_version)
            ->delete();

        if ($removed !== 1) {
            throw new RuntimeException('A managed account changed while its deletion was being applied.');
        }
    }

    private function lockedAccount(ManagedAccountId $target): ?User
    {
        return User::query()->whereKey($target->value)->lockForUpdate()->first();
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
            return new RejectedAccountManagementCommand(
                in_array((string) $failure->getCode(), self::UNIQUE_VIOLATION_STATES, true)
                    ? AccountManagementFailure::Conflict
                    : AccountManagementFailure::Persistence,
            );
        } catch (Throwable) {
            return new RejectedAccountManagementCommand(AccountManagementFailure::Persistence);
        }
    }
}
