<?php

namespace App\Services\AccountManagement;

final readonly class MissingDestructiveConfirmation extends RejectedDestructiveConfirmation
{
    public function __construct()
    {
        parent::__construct('missing');
    }
}
