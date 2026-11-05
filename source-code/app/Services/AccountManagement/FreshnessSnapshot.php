<?php

namespace App\Services\AccountManagement;

use InvalidArgumentException;

/**
 * All state watched by one immutable management command at form-display time.
 * Build its comparison counterpart only after locking the same database rows.
 */
final readonly class FreshnessSnapshot
{
    /**
     * @param  list<FreshnessRecord>  $users
     * @param  list<FreshnessRecord>  $conflictingPivots
     */
    public function __construct(
        public string $operation,
        public int $actorId,
        public int $targetId,
        public array $users,
        public ?FreshnessRecord $organization = null,
        public ?FreshnessSlot $slot = null,
        public array $conflictingPivots = [],
    ) {
        if (trim($operation) === '' || strlen($operation) > 128 || $actorId < 1 || $targetId < 1
            || ($organization !== null && $organization->type !== 'organization')
            || ($slot !== null && ($organization === null || $slot->organizationId !== $organization->id))) {
            throw new InvalidArgumentException('Invalid freshness snapshot.');
        }

        $this->assertRecordList($users, 'user');
        $this->assertRecordList($conflictingPivots, 'pivot');
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'operation' => $this->operation,
            'actor_id' => $this->actorId,
            'target_id' => $this->targetId,
            'users' => $this->sortedPayload($this->users),
            'organization' => $this->organization?->toPayload(),
            'slot' => $this->slot?->toPayload(),
            'conflicting_pivots' => $this->sortedPayload($this->conflictingPivots),
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        if (! is_string($payload['operation'] ?? null) || ! is_int($payload['actor_id'] ?? null)
            || ! is_int($payload['target_id'] ?? null) || ! is_array($payload['users'] ?? null)
            || ! is_array($payload['conflicting_pivots'] ?? null)) {
            throw new InvalidArgumentException('Invalid freshness snapshot payload.');
        }

        $organization = $payload['organization'] ?? null;
        $slot = $payload['slot'] ?? null;

        if (($organization !== null && ! is_array($organization)) || ($slot !== null && ! is_array($slot))) {
            throw new InvalidArgumentException('Invalid freshness snapshot payload.');
        }

        return new self(
            $payload['operation'],
            $payload['actor_id'],
            $payload['target_id'],
            self::recordsFromPayload($payload['users']),
            $organization === null ? null : FreshnessRecord::fromPayload($organization),
            $slot === null ? null : FreshnessSlot::fromPayload($slot),
            self::recordsFromPayload($payload['conflicting_pivots']),
        );
    }

    /** @param array<mixed> $payload @return list<FreshnessRecord> */
    private static function recordsFromPayload(array $payload): array
    {
        if (! array_is_list($payload) || count($payload) > 64) {
            throw new InvalidArgumentException('Invalid freshness record list.');
        }

        $records = [];
        foreach ($payload as $record) {
            if (! is_array($record)) {
                throw new InvalidArgumentException('Invalid freshness record list.');
            }
            $records[] = FreshnessRecord::fromPayload($record);
        }

        return $records;
    }

    /** @param list<FreshnessRecord> $records */
    private function assertRecordList(array $records, string $type): void
    {
        if (! array_is_list($records) || count($records) > 64) {
            throw new InvalidArgumentException('Invalid freshness record list.');
        }

        $ids = [];
        foreach ($records as $record) {
            if (! $record instanceof FreshnessRecord || $record->type !== $type || isset($ids[$record->id])) {
                throw new InvalidArgumentException('Invalid freshness record list.');
            }
            $ids[$record->id] = true;
        }
    }

    /** @param list<FreshnessRecord> $records @return list<array<string, int|string>> */
    private function sortedPayload(array $records): array
    {
        usort($records, static fn (FreshnessRecord $left, FreshnessRecord $right): int => $left->id <=> $right->id);

        return array_map(static fn (FreshnessRecord $record): array => $record->toPayload(), $records);
    }
}
