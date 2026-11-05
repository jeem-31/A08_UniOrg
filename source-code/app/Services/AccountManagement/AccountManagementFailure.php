<?php

namespace App\Services\AccountManagement;

/** Safe, presentation-ready failure categories; no database exception leaks. */
enum AccountManagementFailure: string
{
    case Unauthorized = 'unauthorized';
    case NotFound = 'not_found';
    case Invalid = 'invalid';
    case Ineligible = 'ineligible';
    case Conflict = 'conflict';
    case SelfAction = 'self_action';
    case Stale = 'stale';
    case Confirmation = 'confirmation';
    case Persistence = 'persistence';
}
