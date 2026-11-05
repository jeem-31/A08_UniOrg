<?php

namespace App\Services\AccountManagement;

/** Actions that require an explicit, server-issued destructive confirmation. */
enum DestructiveConfirmationAction: string
{
    case AccountDeletion = 'account.delete';
    case DeanReplacement = 'dean.replace';
}
