<?php

namespace App\Services\DocumentLibrary;

use InvalidArgumentException;
use Stringable;

final readonly class OrganizationFolderIdentity implements Stringable
{
    private function __construct(
        public string $value,
        public ?int $organizationId,
        public ?string $normalizedName,
        public ?string $normalizedAcronym,
    ) {}

    public static function linked(int $organizationId): self
    {
        if ($organizationId < 1) {
            throw new InvalidArgumentException('A linked organization ID must be positive.');
        }

        return new self("linked:{$organizationId}", $organizationId, null, null);
    }

    public static function legacy(string $name, string $acronym): self
    {
        $normalizedName = self::normalizeLegacyPart($name);
        $normalizedAcronym = self::normalizeLegacyPart($acronym);
        $digest = hash('sha256', $normalizedName."\0".$normalizedAcronym);

        return new self("legacy:{$digest}", null, $normalizedName, $normalizedAcronym);
    }

    public static function normalizeLegacyPart(string $value): string
    {
        $collapsed = preg_replace('/[\p{Z}\s]+/u', ' ', $value);

        if ($collapsed === null) {
            throw new InvalidArgumentException('Legacy organization identity must be valid UTF-8.');
        }

        return mb_convert_case(trim($collapsed), MB_CASE_FOLD, 'UTF-8');
    }

    public function isLinked(): bool
    {
        return $this->organizationId !== null;
    }

    public function key(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
