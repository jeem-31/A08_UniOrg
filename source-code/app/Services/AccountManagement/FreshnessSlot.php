<?php

namespace App\Services\AccountManagement;

use InvalidArgumentException;

/** The observed state of one organization-position slot, including an empty slot. */
final readonly class FreshnessSlot
{
    public function __construct(
        public int $organizationId,
        public string $position,
        public ?FreshnessRecord $pivot,
    ) {
        if ($organizationId < 1 || trim($position) === '' || strlen($position) > 128
            || ($pivot !== null && ($pivot->type !== 'pivot' || $pivot->organizationId !== $organizationId))) {
            throw new InvalidArgumentException('Invalid freshness slot.');
        }
    }

    /** @return array{organization_id: int, position: string, pivot: ?array<string, int|string>} */
    public function toPayload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'position' => $this->position,
            'pivot' => $this->pivot?->toPayload(),
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $pivot = $payload['pivot'] ?? null;

        if (! is_int($payload['organization_id'] ?? null) || ! is_string($payload['position'] ?? null)
            || ($pivot !== null && ! is_array($pivot))) {
            throw new InvalidArgumentException('Invalid freshness slot payload.');
        }

        $record = $pivot === null ? null : FreshnessRecord::fromPayload($pivot);

        return new self($payload['organization_id'], $payload['position'], $record);
    }

    public static function empty(int $organizationId, string $position): self
    {
        return new self($organizationId, $position, null);
    }

    public static function occupied(int $organizationId, string $position, FreshnessRecord $pivot): self
    {
        return new self($organizationId, $position, $pivot);
    }
}
