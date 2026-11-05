<?php

namespace App\Services\AccountManagement;

/**
 * Typed managed-account detail outcome. Every variant carries the directory
 * context so recovery links never lose the administrator's search state, and
 * no variant exposes another account, appointment, or organization.
 */
interface AccountDetailsResult
{
    public const NOT_FOUND = 'managed_account_not_found';

    public const ISOLATION_FAILURE = 'managed_account_details_unavailable';

    public const AUDIT_UNAVAILABLE = 'managed_account_audit_unavailable';

    public function resolved(): bool;

    public function context(): DirectoryContext;

    /** Safe presentation reason code, or null when nothing failed. */
    public function reasonCode(): ?string;
}
