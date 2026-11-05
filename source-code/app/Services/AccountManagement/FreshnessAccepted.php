<?php

namespace App\Services\AccountManagement;

final readonly class FreshnessAccepted implements FreshnessVerificationResult
{
    public function accepted(): bool
    {
        return true;
    }
}
