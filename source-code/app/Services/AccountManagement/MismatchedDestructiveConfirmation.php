<?php

namespace App\Services\AccountManagement;

final readonly class MismatchedDestructiveConfirmation extends RejectedDestructiveConfirmation
{
    public function __construct()
    {
        parent::__construct('mismatched');
    }
}
