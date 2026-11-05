<?php

namespace App\Services\AccountManagement;

use InvalidArgumentException;

/** Immutable former-Dean transition that a replacement confirmation binds. */
final readonly class NonDeanOutcome
{
    private const DEACTIVATE = 'deactivate';

    private const REASSIGN = 'reassign';

    /** @var list<string> */
    private const ASSIGNABLE_ROLES = ['osas_admin', 'admin', 'student'];

    private function __construct(public string $type, public ?string $role) {}

    public static function deactivate(): self
    {
        return new self(self::DEACTIVATE, null);
    }

    public static function reassign(string $role): self
    {
        if (! in_array($role, self::ASSIGNABLE_ROLES, true)) {
            throw new InvalidArgumentException('The former Dean role must be assignable and non-Dean.');
        }

        return new self(self::REASSIGN, $role);
    }

    /** @return array{type: string, role: ?string} */
    public function toPayload(): array
    {
        return ['type' => $this->type, 'role' => $this->role];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        if (array_keys($payload) !== ['type', 'role'] || ! is_string($payload['type'])
            || ($payload['role'] !== null && ! is_string($payload['role']))) {
            throw new InvalidArgumentException('Invalid former Dean outcome.');
        }

        return match ($payload['type']) {
            self::DEACTIVATE => $payload['role'] === null
                ? self::deactivate()
                : throw new InvalidArgumentException('Invalid former Dean outcome.'),
            self::REASSIGN => $payload['role'] !== null
                ? self::reassign($payload['role'])
                : throw new InvalidArgumentException('Invalid former Dean outcome.'),
            default => throw new InvalidArgumentException('Invalid former Dean outcome.'),
        };
    }
}
