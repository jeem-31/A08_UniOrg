<?php

namespace App\Services\AccountManagement;

final readonly class ConsumedDestructiveConfirmation extends RejectedDestructiveConfirmation
{
    public function __construct()
    {
        parent::__construct('consumed');
    }
}
