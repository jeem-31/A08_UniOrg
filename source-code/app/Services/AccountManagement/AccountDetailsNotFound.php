<?php

namespace App\Services\AccountManagement;

/** The requested Managed_Account does not exist; no substitute account is offered. */
final readonly class AccountDetailsNotFound implements AccountDetailsResult
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
        return self::NOT_FOUND;
    }
}
