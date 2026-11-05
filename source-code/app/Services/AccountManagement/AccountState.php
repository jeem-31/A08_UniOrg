<?php

namespace App\Services\AccountManagement;

/** The observable Account_State of a managed account, derived from `is_active`. */
enum AccountState: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public static function fromActiveFlag(bool $isActive): self
    {
        return $isActive ? self::Active : self::Inactive;
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
