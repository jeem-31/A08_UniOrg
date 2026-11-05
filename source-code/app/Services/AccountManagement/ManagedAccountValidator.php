<?php

namespace App\Services\AccountManagement;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Applies the existing UniOrg User field validation and credential policy to
 * managed-account input, and resolves email/student-ID duplication against
 * rows already locked by the caller transaction.
 *
 * Every returned message is field-scoped and non-sensitive: submitted values,
 * credentials, and database errors are never echoed back to the caller.
 */
final readonly class ManagedAccountValidator
{
    /** @var array<string, list<string>> */
    private const PROFILE_RULES = [
        'first_name' => ['required', 'string', 'max:255'],
        'middle_name' => ['nullable', 'string', 'max:255'],
        'middle_initial' => ['nullable', 'string', 'max:10'],
        'last_name' => ['required', 'string', 'max:255'],
        'suffix' => ['nullable', 'string', 'max:50'],
        'email' => ['required', 'string', 'email', 'max:255'],
        'student_id' => ['nullable', 'string', 'max:255'],
        'course' => ['nullable', 'string', 'max:255'],
        'year_level' => ['nullable', 'string', 'max:255'],
        'section' => ['nullable', 'string', 'max:255'],
        'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        'photo' => ['nullable', 'string', 'max:255'],
    ];

    /** @var list<string> */
    private const REQUIRED_ON_CREATE = ['first_name', 'last_name', 'email'];

    /** @var list<string> The existing UniOrg credential policy for a new password. */
    private const CREDENTIAL_RULES = ['required', 'string', 'min:8', 'max:255'];

    /**
     * Validates every field a create submission must satisfy.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, list<string>>
     */
    public function validateNewAccount(array $values, NewAccountCredential $credential, ?string $role = null): array
    {
        $rules = self::PROFILE_RULES;

        if ($role === AssignableNonDeanRole::SuperAdmin->value) {
            $rules['department_id'] = ['required', 'integer', 'exists:departments,id'];
        }

        foreach (self::REQUIRED_ON_CREATE as $field) {
            $values[$field] ??= null;
            $rules[$field] = self::PROFILE_RULES[$field];
        }

        $messages = $this->messages(
            array_merge($values, ['password' => $credential->plainText()]),
            array_merge($this->scopedRules($rules, $values), ['password' => self::CREDENTIAL_RULES]),
        );

        if ($role === AssignableNonDeanRole::SuperAdmin->value) {
            $messages = array_merge($messages, $this->deanSlotMessages($values));
        }

        return $messages;
    }

    /**
     * A college can hold at most one active Dean, so a new Dean account is
     * rejected when its department already has one.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, list<string>>
     */
    private function deanSlotMessages(array $values): array
    {
        $departmentId = $values['department_id'] ?? null;

        if (! is_numeric($departmentId) || (int) $departmentId <= 0) {
            return [];
        }

        $activeDeanExists = User::query()
            ->where('role', AssignableNonDeanRole::SuperAdmin->value)
            ->where('is_active', DB::raw('TRUE'))
            ->where('department_id', (int) $departmentId)
            ->exists();

        return $activeDeanExists
            ? ['department_id' => ['This college already has an active Dean.']]
            : [];
    }

    /**
     * Validates only the profile fields an edit submission actually supplied.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, list<string>>
     */
    public function validateProfilePatch(array $values): array
    {
        return $this->messages($values, $this->scopedRules(self::PROFILE_RULES, $values));
    }

    /**
     * Resolves duplicated identities case-insensitively for email and, when
     * supplied, the student ID. Run this inside the mutation transaction.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, list<string>>
     */
    public function duplicateIdentityMessages(array $values, ?int $ignoreUserId): array
    {
        $messages = [];

        if (array_key_exists('email', $values) && is_string($values['email']) && $values['email'] !== '') {
            if ($this->identityTaken('email', mb_strtolower($values['email']), $ignoreUserId, true)) {
                $messages['email'] = ['This email address already belongs to another account.'];
            }
        }

        if (array_key_exists('student_id', $values) && is_string($values['student_id']) && $values['student_id'] !== '') {
            if ($this->identityTaken('student_id', $values['student_id'], $ignoreUserId, false)) {
                $messages['student_id'] = ['This student ID already belongs to another account.'];
            }
        }

        return $messages;
    }

    private function identityTaken(string $column, string $value, ?int $ignoreUserId, bool $caseInsensitive): bool
    {
        $query = User::query();

        if ($caseInsensitive) {
            $query->whereRaw("LOWER({$column}) = ?", [$value]);
        } else {
            $query->where($column, $value);
        }

        if ($ignoreUserId !== null) {
            $query->whereKeyNot($ignoreUserId);
        }

        return $query->exists();
    }

    /**
     * @param  array<string, list<string>>  $rules
     * @param  array<string, mixed>  $values
     * @return array<string, list<string>>
     */
    private function scopedRules(array $rules, array $values): array
    {
        return array_intersect_key($rules, $values);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, list<string>>  $rules
     * @return array<string, list<string>>
     */
    private function messages(array $values, array $rules): array
    {
        $validator = Validator::make(array_intersect_key($values, $rules), $rules);

        if ($validator->passes()) {
            return [];
        }

        /** @var array<string, list<string>> $failures */
        $failures = $validator->errors()->toArray();

        return $failures;
    }
}
