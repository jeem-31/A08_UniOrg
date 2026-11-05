<?php

namespace App\Services\AccountManagement;

/**
 * Target isolation could not be proven, so the read fails closed: only a
 * generic reason code and the directory context are returned.
 */
final readonly class AccountDetailsIsolationFailure implements AccountDetailsResult
{
    private function __construct(private DirectoryContext $context) {}

    public static function for(DirectoryContext $context): self
    {
        return new self($context);
    }

    public function resolved(): bool
    {
        return false;
    }

    public function context(): DirectoryContext
    {
        return $this->context;
    }

    public function reasonCode(): ?string
    {
        return self::ISOLATION_FAILURE;
    }
}
