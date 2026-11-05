<?php

namespace App\Services\AccountManagement;

interface DestructiveConfirmationVerificationResult
{
    public function accepted(): bool;
}
