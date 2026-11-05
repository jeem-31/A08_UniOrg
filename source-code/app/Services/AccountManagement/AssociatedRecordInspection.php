<?php

namespace App\Services\AccountManagement;

/**
 * Safe deletion eligibility projection. It deliberately contains no record,
 * table, column, association, or database-error details.
 */
final readonly class AssociatedRecordInspection
{
    public const ELIGIBLE = 'eligible';

    public const ASSOCIATED_RECORDS = 'associated_records';

    public const RETENTION_ACTIVE = 'deletion_retention_active';

    public const UNAVAILABLE = 'deletion_inspection_unavailable';

    private function __construct(private string $reasonCode) {}

    public static function eligible(): self
    {
        return new self(self::ELIGIBLE);
    }

    public static function blocked(string $reasonCode): self
    {
        return new self($reasonCode);
    }

    public function canBeDeleted(): bool
    {
        return $this->reasonCode === self::ELIGIBLE;
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }
}
