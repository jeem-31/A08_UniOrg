<?php

namespace App\Services\AccountManagement;

/**
 * Every System_Access_Role a managed account can hold.
 *
 * Directory filtering may select any of these, including the Dean marker, while
 * assignment commands remain restricted to {@see AssignableNonDeanRole}.
 */
enum SystemAccessRole: string
{
    case OsasAdmin = 'osas_admin';
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::OsasAdmin => 'OSAS Admin',
            self::SuperAdmin => 'Dean',
            self::Admin => 'Admin',
            self::Student => 'Student',
        };
    }
}
