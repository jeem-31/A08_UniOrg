<?php

namespace App\Services\AccountManagement;

final readonly class CanceledDestructiveConfirmation extends RejectedDestructiveConfirmation
{
    public function __construct()
    {
        parent::__construct('canceled');
    }
}
