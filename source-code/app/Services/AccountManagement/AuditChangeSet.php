<?php

namespace App\Services\AccountManagement;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * The strict allow list of before/after values a completed action may record.
 *
 * Only the documented top-level keys exist, every profile key must be an
 * editable non-credential field, and any credential-derived key or hashed
 * value is rejected before persistence or presentation.
 */
final readonly class AuditChangeSet
{
    public const STATE_ACTIVE = 'Active';

    public const STATE_INACTIVE = 'Inactive';

    /** @var list<string> */
    private const TOP_LEVEL_KEYS = [
        'profile',
        'role',
        'state',
        'organization',
        'position',
        'incoming_dean',
        'former_dean',
        'non_dean_outcome',
        'former_assignee',
        'selected_assignee',
        'deleted_account',
    ];

    /** @var list<string> */
    private const SYSTEM_ROLES = ['osas_admin', 'super_admin', 'admin', 'student'];

    /** @var list<string> */
    private const CREDENTIAL_KEY_FRAGMENTS = [
        'password', 'passwd', 'pwd', 'credential', 'secret', 'token',
        'hash', 'reset', 'salt', 'bcrypt', 'argon', 'remember',
    ];

    /** @var list<string> */
    private const HASH_VALUE_PREFIXES = ['$2y$', '$2a$', '$2b$', '$argon2i$', '$argon2id$'];

    /** @param array<string, mixed> $payload */
    private function __construct(private array $payload) {}

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Records only the editable profile fields whose value actually changed.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function withProfileDiff(array $before, array $after): self
    {
        self::assertProfileFields($before);
        self::assertProfileFields($after);

        $diff = [];

        foreach ($after as $field => $value) {
            $previous = self::normalizeValue($before[$field] ?? null);
            $current = self::normalizeValue($value);

            if ($previous !== $current) {
                $diff[$field] = ['before' => $previous, 'after' => $current];
            }
        }

        if ($diff === []) {
            return $this;
        }

        ksort($diff);

        return $this->with('profile', $diff);
    }

    public function withRoleChange(?string $before, string $after): self
    {
        foreach ([$before, $after] as $role) {
            if ($role !== null && ! in_array($role, self::SYSTEM_ROLES, true)) {
                throw new InvalidArgumentException('An audited role must be an existing system access role.');
            }
        }

        return $this->with('role', ['before' => $before, 'after' => $after]);
    }

    public function withStateChange(?bool $before, bool $after): self
    {
        return $this->with('state', [
            'before' => $before === null ? null : self::state($before),
            'after' => self::state($after),
        ]);
    }

    public function withOrganization(AuditIdentity $organization): self
    {
        return $this->with('organization', $organization->toPayload());
    }

    public function withPosition(AdviserPosition|OfficerPosition $position): self
    {
        return $this->with('position', $position->role());
    }

    public function withDeanIdentities(AuditIdentity $incoming, ?AuditIdentity $former = null): self
    {
        $changes = $this->with('incoming_dean', $incoming->toPayload());

        return $former === null ? $changes : $changes->with('former_dean', $former->toPayload());
    }

    public function withNonDeanOutcome(NonDeanOutcome $outcome): self
    {
        return $this->with('non_dean_outcome', $outcome->toPayload());
    }

    public function withAppointmentAssignees(?AuditIdentity $former, AuditIdentity $selected): self
    {
        $changes = $former === null ? $this : $this->with('former_assignee', $former->toPayload());

        return $changes->with('selected_assignee', $selected->toPayload());
    }

    public function withDeletedAccount(AuditIdentity $deleted): self
    {
        return $this->with('deleted_account', $deleted->toPayload());
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->payload);
    }

    public function isEmpty(): bool
    {
        return $this->payload === [];
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        self::assertCredentialFree($this->payload);

        return $this->payload;
    }

    private function with(string $key, array|string|null $value): self
    {
        if (! in_array($key, self::TOP_LEVEL_KEYS, true)) {
            throw new InvalidArgumentException('An audited change key must be within the completed-action allow list.');
        }

        $payload = $this->payload;
        $payload[$key] = $value;

        self::assertCredentialFree($payload);

        return new self($payload);
    }

    private static function state(bool $isActive): string
    {
        return $isActive ? self::STATE_ACTIVE : self::STATE_INACTIVE;
    }

    /** @param array<string, mixed> $values */
    private static function assertProfileFields(array $values): void
    {
        foreach (array_keys($values) as $field) {
            if (! is_string($field) || ! in_array($field, ManagedAccountProfile::editableFields(), true)) {
                self::assertCredentialFree([(string) $field => null]);

                throw new InvalidArgumentException('An audited profile field must be an editable managed-account field.');
            }
        }
    }

    private static function normalizeValue(mixed $value): string|int|float|bool|null
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('An audited value must be a scalar, null, or a timestamp.');
        }

        self::assertNotHashedValue($value);

        return $value;
    }

    /** @param array<array-key, mixed> $payload */
    private static function assertCredentialFree(array $payload): void
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && self::isCredentialKey($key)) {
                throw new InvalidArgumentException('Credential-derived data cannot be recorded in a completed action.');
            }

            if (is_array($value)) {
                self::assertCredentialFree($value);

                continue;
            }

            if (is_string($value)) {
                self::assertNotHashedValue($value);
            }
        }
    }

    private static function isCredentialKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::CREDENTIAL_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private static function assertNotHashedValue(string $value): void
    {
        foreach (self::HASH_VALUE_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                throw new InvalidArgumentException('Credential-derived data cannot be recorded in a completed action.');
            }
        }
    }
}
