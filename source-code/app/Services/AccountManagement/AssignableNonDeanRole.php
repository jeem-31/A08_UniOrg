<?php

namespace App\Services\AccountManagement;

/** Roles permitted for account creation, ordinary edits, and former-Dean reassignment. */
enum AssignableNonDeanRole: string
{
    case SuperAdmin = 'super_admin';
    case OsasAdmin = 'osas_admin';
    case Admin = 'admin';
    case Student = 'student';
}
