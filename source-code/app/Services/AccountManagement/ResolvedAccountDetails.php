<?php

namespace App\Services\AccountManagement;

use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Target-scoped, credential-free detail projection for exactly one Managed_Account.
 *
 * Construction enforces the isolation and audit bounds: every assignment and
 * audit entry must belong to the requested account, and at most the ten newest
 * audit entries may be carried. Only whitelisted editable profile values are
 * projected, so no credential attribute can reach a view.
 */
final readonly class ResolvedAccountDetails implements AccountDetailsResult
{
    public const MAX_AUDIT_ENTRIES = 10;

    /**
     * @param  array<string, mixed>  $profileValues
     * @param  list<ManagedAccountAssignmentSummary>  $assignments
     * @param  list<AuditEntryView>  $auditEntries
     */
    private function __construct(
        private DirectoryContext $context,
        public int $accountId,
        public string $fullName,
        public array $profileValues,
        public string $role,
        public ?SystemAccessRole $systemAccessRole,
        public AccountState $state,
        public DeanAppointmentStatus $deanStatus,
        public int $managementVersion,
        public ?CarbonInterface $expiresAt,
        public array $assignments,
        public array $auditEntries,
        public bool $auditAvailable,
    ) {}

    /**
     * @param  list<ManagedAccountAssignmentSummary>  $assignments
     * @param  list<AuditEntryView>|null  $auditEntries  Null when Audit_Entries could not be retrieved.
     */
    public static function forTarget(
        User $user,
        array $assignments,
        ?array $auditEntries,
        DirectoryContext $context,
    ): self {
        $accountId = (int) $user->getKey();

        if ($accountId < 1) {
            throw new InvalidArgumentException('A detail projection requires a persisted account.');
        }

        foreach ($assignments as $assignment) {
            if ($assignment->userId !== $accountId) {
                throw new InvalidArgumentException('A detail projection cannot carry another account’s assignment.');
            }
        }

        $entries = $auditEntries ?? [];

        if (count($entries) > self::MAX_AUDIT_ENTRIES) {
            throw new InvalidArgumentException('A detail projection carries at most ten audit entries.');
        }

        foreach ($entries as $entry) {
            if ($entry->affectedAccountId !== $accountId) {
                throw new InvalidArgumentException('A detail projection cannot carry another account’s audit entry.');
            }
        }

        $role = (string) $user->role;

        return new self(
            $context,
            $accountId,
            trim((string) $user->full_name),
            self::profileValues($user),
            $role,
            SystemAccessRole::tryFrom($role),
            AccountState::fromActiveFlag((bool) $user->is_active),
            DeanAppointmentStatus::forUser($user),
            (int) $user->management_version,
            $user->expires_at,
            array_values($assignments),
            array_values($entries),
            $auditEntries !== null,
        );
    }

    public function resolved(): bool
    {
        return true;
    }

    public function context(): DirectoryContext
    {
        return $this->context;
    }

    public function reasonCode(): ?string
    {
        return $this->auditAvailable ? null : self::AUDIT_UNAVAILABLE;
    }

    public function active(): bool
    {
        return $this->state->isActive();
    }

    /** The displayed Account_State label. */
    public function stateLabel(): string
    {
        return $this->state->isActive() ? 'Active' : 'Inactive';
    }

    /**
     * Only the whitelisted editable, non-credential profile fields are projected.
     *
     * @return array<string, mixed>
     */
    private static function profileValues(User $user): array
    {
        $values = [];

        foreach (ManagedAccountProfile::editableFields() as $field) {
            $values[$field] = $user->getAttribute($field);
        }

        return $values;
    }
}
