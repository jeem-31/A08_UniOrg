<?php

namespace App\Services\AccountManagement;

use InvalidArgumentException;

/** Whitelisted non-credential profile input for create and patch commands. */
final readonly class ManagedAccountProfile
{
    private const EDITABLE_FIELDS = [
        'first_name', 'middle_name', 'middle_initial', 'last_name', 'suffix', 'email',
        'student_id', 'course', 'year_level', 'section', 'department_id', 'photo',
    ];

    /** @param array<string, mixed> $values */
    private function __construct(private array $values) {}

    /** @param array<string, mixed> $validatedValues */
    public static function fromValidated(array $validatedValues): self
    {
        if ($validatedValues === [] || array_diff(array_keys($validatedValues), self::EDITABLE_FIELDS) !== []) {
            throw new InvalidArgumentException('Profile input contains fields outside the managed-account allow list.');
        }

        return new self($validatedValues);
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * The single source of the editable, non-credential profile allow list.
     *
     * @return list<string>
     */
    public static function editableFields(): array
    {
        return self::EDITABLE_FIELDS;
    }
}
