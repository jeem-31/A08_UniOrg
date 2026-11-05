<?php

namespace App\Services\AccountManagement;

final readonly class StaleSubmission implements FreshnessVerificationResult
{
    public const INVALID = 'invalid';

    public const EXPIRED = 'expired';

    public const MISMATCH = 'mismatch';

    public function __construct(
        public string $reason,
        public ReloadAffordance $reload = new ReloadAffordance,
    ) {}

    public function accepted(): bool
    {
        return false;
    }
}
