<?php

namespace App\Services\AccountManagement;

final readonly class DestructiveConfirmationAccepted implements DestructiveConfirmationVerificationResult
{
    public function accepted(): bool
    {
        return true;
    }
}
