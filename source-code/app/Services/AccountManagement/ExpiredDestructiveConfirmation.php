<?php

namespace App\Services\AccountManagement;

final readonly class ExpiredDestructiveConfirmation extends RejectedDestructiveConfirmation
{
    public function __construct()
    {
        parent::__construct('expired');
    }
}
