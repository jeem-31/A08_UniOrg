<?php

namespace App\Services\AccountManagement;

use InvalidArgumentException;

/** Immutable version observed for one locked managed record. */
final readonly class FreshnessRecord
{
    private const USER = 'user';

    private const ORGANIZATION = 'organization';

    private const PIVOT = 'pivot';

    private function __construct(
        public string $type,
        public int $id,
        public int $version,
        public ?int $organizationId = null,
        public ?int $userId = null,
    ) {
        if (! in_array($type, [self::USER, self::ORGANIZATION, self::PIVOT], true)
            || $id < 1 || $version < 1
            || ($type === self::PIVOT && ($organizationId === null || $organizationId < 1 || $userId === null || $userId < 1))
            || ($type !== self::PIVOT && ($organizationId !== null || $userId !== null))) {
            throw new InvalidArgumentException('Invalid freshness record.');
        }
    }

    public static function user(int $id, int $version): self
    {
        return new self(self::USER, $id, $version);
    }

    public static function organization(int $id, int $version): self
    {
        return new self(self::ORGANIZATION, $id, $version);
    }

    public static function pivot(int $id, int $version, int $organizationId, int $userId): self
    {
        return new self(self::PIVOT, $id, $version, $organizationId, $userId);
    }

    /** @return array<string, int|string> */
    public function toPayload(): array
    {
        $payload = ['type' => $this->type, 'id' => $this->id, 'version' => $this->version];

        if ($this->type === self::PIVOT) {
            $payload['organization_id'] = $this->organizationId;
            $payload['user_id'] = $this->userId;
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        if (! is_string($payload['type'] ?? null) || ! is_int($payload['id'] ?? null)
            || ! is_int($payload['version'] ?? null)) {
            throw new InvalidArgumentException('Invalid freshness record payload.');
        }

        return match ($payload['type']) {
            self::USER => self::user($payload['id'], $payload['version']),
            self::ORGANIZATION => self::organization($payload['id'], $payload['version']),
            self::PIVOT => self::pivot(
                $payload['id'],
                $payload['version'],
                self::requiredPositiveInt($payload, 'organization_id'),
                self::requiredPositiveInt($payload, 'user_id'),
            ),
            default => throw new InvalidArgumentException('Unknown freshness record type.'),
        };
    }

    /** @param array<string, mixed> $payload */
    private static function requiredPositiveInt(array $payload, string $key): int
    {
        if (! is_int($payload[$key] ?? null) || $payload[$key] < 1) {
            throw new InvalidArgumentException('Invalid freshness record payload.');
        }

        return $payload[$key];
    }
}
