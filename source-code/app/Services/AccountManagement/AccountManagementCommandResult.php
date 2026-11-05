<?php

namespace App\Services\AccountManagement;

/** Typed service outcome consumed by controllers instead of exceptions or raw models. */
interface AccountManagementCommandResult
{
    public function completed(): bool;
}
