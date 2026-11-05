<?php

namespace App\Services\AccountManagement;

interface FreshnessVerificationResult
{
    public function accepted(): bool;
}
