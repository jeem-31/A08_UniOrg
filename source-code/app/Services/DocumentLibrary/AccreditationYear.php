<?php

namespace App\Services\DocumentLibrary;

use Stringable;

final readonly class AccreditationYear implements Stringable
{
    private function __construct(
        public int $startingYear,
        public int $endingYear,
        public string $value,
    ) {}

    public static function parse(?string $value): ?self
    {
        if ($value === null || preg_match('/^(\d{4})-(\d{4})$/D', $value, $matches) !== 1) {
            return null;
        }

        $startingYear = (int) $matches[1];
        $endingYear = (int) $matches[2];

        if ($endingYear !== $startingYear + 1) {
            return null;
        }

        return new self($startingYear, $endingYear, $value);
    }

    public static function fromCurrentOutput(?string $value): self|UnspecifiedYear
    {
        return self::parse($value) ?? UnspecifiedYear::Value;
    }

    public function key(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
