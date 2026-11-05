<?php

namespace App\Services\AccountManagement;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * An immutable description of one completed OSAS account-management action.
 *
 * A completed action can only be constructed from a named factory, so every
 * recorded payload carries exactly the data its action family requires and
 * nothing derived from a credential.
 */
final readonly class CompletedAction
{
    private function __construct(
        public AccountManagementAction $action,
        public AuditIdentity $actor,
        public AuditIdentity $affected,
        public ?AuditIdentity $organization,
        public AuditChangeSet $changes,
        public ?DateTimeImmutable $occurredAt,
    ) {
        if ($action->isAppointment() !== ($organization !== null)) {
            throw new InvalidArgumentException('Only appointment actions record an organization.');
        }

        if ($action->isAppointment() && ! $changes->has('position')) {
            throw new InvalidArgumentException('An appointment action must record its canonical position.');
        }

        if ($action->isDeanTransition() && ! $changes->has('incoming_dean')) {
            throw new InvalidArgumentException('A Dean transition must record the incoming Dean identity.');
        }

        if ($action === AccountManagementAction::AccountDeleted && ! $changes->has('deleted_account')) {
            throw new InvalidArgumentException('A deletion must record its redacted account snapshot.');
        }

        if ($changes->isEmpty()) {
            throw new InvalidArgumentException('A completed action must record at least one allow-listed change.');
        }
    }

    /**
     * @param  array<string, mixed>  $profileValues  Whitelisted non-credential profile values.
     */
    public static function accountCreated(
        AuditIdentity $actor,
        AuditIdentity $affected,
        array $profileValues,
        AssignableNonDeanRole $role,
        ?DateTimeInterface $occurredAt = null,
    ): self {
        return new self(
            AccountManagementAction::AccountCreated,
            $actor,
            $affected,
            null,
            AuditChangeSet::empty()
                ->withProfileDiff([], $profileValues)
                ->withRoleChange(null, $role->value)
                ->withStateChange(null, true),
            self::instant($occurredAt),
        );
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function profileUpdated(
        AuditIdentity $actor,
        AuditIdentity $affected,
        array $before,
        array $after,
        ?DateTimeInterface $occurredAt = null,
    ): self {
        return new self(
            AccountManagementAction::ProfileUpdated,
            $actor,
            $affected,
            null,
            AuditChangeSet::empty()->withProfileDiff($before, $after),
            self::instant($occurredAt),
        );
    }

    public static function roleChanged(
        AuditIdentity $actor,
        AuditIdentity $affected,
        string $previousRole,
        AssignableNonDeanRole $role,
        ?DateTimeInterface $occurredAt = null,
    ): self {
        return new self(
            AccountManagementAction::RoleChanged,
            $actor,
            $affected,
            null,
            AuditChangeSet::empty()->withRoleChange($previousRole, $role->value),
            self::instant($occurredAt),
        );
    }

    public static function accountDeactivated(
        AuditIdentity $actor,
        AuditIdentity $affected,
        ?DateTimeInterface $occurredAt = null,
    ): self {
        return new self(
            AccountManagementAction::AccountDeactivated,
            $actor,
            $affected,
            null,
            AuditChangeSet::empty()->withStateChange(true, false),
            self::instant($occurredAt),
        );
    }

    public static function accountReactivated(
        AuditIdentity $actor,
        AuditIdentity $affected,
        ?DateTimeInterface $occurredAt = null,
    ): self {
        return new self(
            AccountManagementAction::AccountReactivated,
            $actor,
            $affected,
            null,
            AuditChangeSet::empty()->withStateChange(false, true),
            self::instant($occurredAt),
        );
    }

    public static function accountExpired(
        AuditIdentity $affected,
        ?DateTimeInterface $occurredAt = null,
    ): self {
        return new self(
            AccountManagementAction::AccountExpired,
            AuditIdentity::of(null, 'System (auto-expire)'),
            $affected,
            null,
            AuditChangeSet::empty()->withStateChange(true, false),
            self::instant($occurredAt),
        );
    }

    public static function accountDeleted(
        AuditIdentity $actor,
        AuditIdentity $affected,
        ?DateTimeInterface $occurredAt = null,
    ): self {
        return new self(
            AccountManagementAction::AccountDeleted,
            $actor,
            $affected,
            null,
            AuditChangeSet::empty()->withDeletedAccount($affected),
            self::instant($occurredAt),
        );
    }

    public static function deanAssigned(
        AuditIdentity $actor,
        AuditIdentity $incomingDean,
        string $previousRole,
        ?DateTimeInterface $occurredAt = null,
    ): self {
        return new self(
            AccountManagementAction::DeanAssigned,
            $actor,
            $incomingDean,
            null,
            AuditChangeSet::empty()
                ->withDeanIdentities($incomingDean)
                ->withRoleChange($previousRole, 'super_admin'),
            self::instant($occurredAt),
        );
    }

    public static function deanReplaced(
        AuditIdentity $actor,
        AuditIdentity $incomingDean,
        string $incomingPreviousRole,
        AuditIdentity $formerDean,
        NonDeanOutcome $outcome,
        ?DateTimeInterface $occurredAt = null,
    ): self {
        return new self(
            AccountManagementAction::DeanReplaced,
            $actor,
            $incomingDean,
            null,
            AuditChangeSet::empty()
                ->withDeanIdentities($incomingDean, $formerDean)
                ->withRoleChange($incomingPreviousRole, 'super_admin')
                ->withNonDeanOutcome($outcome),
            self::instant($occurredAt),
        );
    }

    public static function adviserAppointed(
        AuditIdentity $actor,
        AuditIdentity $organization,
        AdviserPosition $position,
        ?AuditIdentity $formerAssignee,
        AuditIdentity $selectedAssignee,
        string $previousRole,
        string $resultingRole,
        ?DateTimeInterface $occurredAt = null,
    ): self {
        return self::appointed(
            AccountManagementAction::AdviserAppointed,
            $actor,
            $organization,
            $position,
            $formerAssignee,
            $selectedAssignee,
            $previousRole,
            $resultingRole,
            $occurredAt,
        );
    }

    public static function officerAppointed(
        AuditIdentity $actor,
        AuditIdentity $organization,
        OfficerPosition $position,
        ?AuditIdentity $formerAssignee,
        AuditIdentity $selectedAssignee,
        string $previousRole,
        string $resultingRole,
        ?DateTimeInterface $occurredAt = null,
    ): self {
        return self::appointed(
            AccountManagementAction::OfficerAppointed,
            $actor,
            $organization,
            $position,
            $formerAssignee,
            $selectedAssignee,
            $previousRole,
            $resultingRole,
            $occurredAt,
        );
    }

    private static function appointed(
        AccountManagementAction $action,
        AuditIdentity $actor,
        AuditIdentity $organization,
        AdviserPosition|OfficerPosition $position,
        ?AuditIdentity $formerAssignee,
        AuditIdentity $selectedAssignee,
        string $previousRole,
        string $resultingRole,
        ?DateTimeInterface $occurredAt,
    ): self {
        return new self(
            $action,
            $actor,
            $selectedAssignee,
            $organization,
            AuditChangeSet::empty()
                ->withOrganization($organization)
                ->withPosition($position)
                ->withAppointmentAssignees($formerAssignee, $selectedAssignee)
                ->withRoleChange($previousRole, $resultingRole),
            self::instant($occurredAt),
        );
    }

    private static function instant(?DateTimeInterface $occurredAt): ?DateTimeImmutable
    {
        return $occurredAt === null
            ? null
            : DateTimeImmutable::createFromInterface($occurredAt);
    }
}
