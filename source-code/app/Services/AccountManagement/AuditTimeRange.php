<?php

namespace App\Services\AccountManagement;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * An accepted audit time range boundary pair.
 *
 * A range can only exist when its end is not before its start, so the query
 * layer can never silently reorder or widen a submitted range.
 */
final readonly class AuditTimeRange
{
    private function __construct(public ?CarbonImmutable $start, public ?CarbonImmutable $end) {}

    public static function of(?DateTimeInterface $start, ?DateTimeInterface $end): self
    {
        $from = $start === null ? null : CarbonImmutable::instance($start);
        $to = $end === null ? null : CarbonImmutable::instance($end);

        if ($from !== null && $to !== null && $to->lessThan($from)) {
            throw new InvalidArgumentException('An audit time range end cannot precede its start.');
        }

        return new self($from, $to);
    }

    public static function isReversed(?DateTimeInterface $start, ?DateTimeInterface $end): bool
    {
        if ($start === null || $end === null) {
            return false;
        }

        return CarbonImmutable::instance($end)->lessThan(CarbonImmutable::instance($start));
    }

    public function isEmpty(): bool
    {
        return $this->start === null && $this->end === null;
    }

    public function contains(DateTimeInterface $moment): bool
    {
        $instant = CarbonImmutable::instance($moment);

        if ($this->start !== null && $instant->lessThan($this->start)) {
            return false;
        }

        return $this->end === null || ! $instant->greaterThan($this->end);
    }
}
