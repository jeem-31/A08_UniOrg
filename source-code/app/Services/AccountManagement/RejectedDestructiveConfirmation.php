<?php

namespace App\Services\AccountManagement;

abstract readonly class RejectedDestructiveConfirmation implements DestructiveConfirmationVerificationResult
{
    public function __construct(public string $reason) {}

    public function accepted(): bool
    {
        return false;
    }
}
