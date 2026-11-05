<?php

namespace App\Services\DocumentLibrary;

use InvalidArgumentException;

final readonly class AuthorizedFilterOptions
{
    /** @var array<string, list<string>> */
    private array $options;

    /** @var array<string, array<string, string>> */
    private array $labels;

    /**
     * @param list<string> $departments
     * @param list<string> $years
     * @param list<string> $requirements
     * @param list<string> $documentTypes
     * @param list<string> $applicationKinds
     * @param list<string> $applicationStatuses
     * @param array<string, array<string, string>> $labels
     */
    public function __construct(
        array $departments = [],
        array $years = [],
        array $requirements = [],
        array $documentTypes = [],
        array $applicationKinds = [],
        array $applicationStatuses = [],
        array $labels = [],
    ) {
        $this->options = [
            LibraryFilterCategory::Department->value => self::normalize($departments),
            LibraryFilterCategory::AccreditationYear->value => self::normalize($years),
            LibraryFilterCategory::DocumentRequirement->value => self::normalize($requirements),
            LibraryFilterCategory::DocumentType->value => self::normalize($documentTypes),
            LibraryFilterCategory::ApplicationKind->value => self::normalize($applicationKinds),
            LibraryFilterCategory::ApplicationStatus->value => self::normalize($applicationStatuses),
        ];
        $this->labels = $labels;
    }

    /** @return list<string> */
    public function for(LibraryFilterCategory $category): array
    {
        return $this->options[$category->value];
    }

    public function contains(LibraryFilterCategory $category, string $value): bool
    {
        return in_array($value, $this->for($category), true);
    }

    /** @return array<string, list<string>> */
    public function all(): array
    {
        return $this->options;
    }

    /** @return array<string, array<string, string>> */
    public function labels(): array
    {
        return $this->labels;
    }

    /** @param array<array-key, mixed> $values
     * @return list<string>
     */
    private static function normalize(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                throw new InvalidArgumentException('Authorized library filter options must be non-empty strings.');
            }

            if (! in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }
}
