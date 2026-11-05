<?php

namespace App\Services\AccountManagement;

/**
 * Immutable safe failure result, optionally carrying the existing reload affordance
 * and one non-sensitive message list per rejected or duplicated field.
 */
final readonly class RejectedAccountManagementCommand implements AccountManagementCommandResult
{
    /** @param array<string, list<string>> $fieldErrors */
    public function __construct(
        public AccountManagementFailure $failure,
        public ?ReloadAffordance $reload = null,
        public array $fieldErrors = [],
    ) {}

    public function completed(): bool
    {
        return false;
    }
}
