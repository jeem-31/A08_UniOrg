<?php

namespace App\Services\AccountManagement;

/** The only lifecycle transitions an OSAS account-management command may request. */
enum AccountLifecycleAction: string
{
    case Activate = 'activate';
    case Deactivate = 'deactivate';
}
