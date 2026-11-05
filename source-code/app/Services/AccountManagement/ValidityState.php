<?php

namespace App\Services\AccountManagement;

/**
 * The time-based validity of a student account, derived solely from its fixed
 * `expires_at` moment and the clock. It is independent of `is_active`, which
 * remains the manual lifecycle flag.
 */
enum ValidityState: string
{
    /** The account is still within its fixed validity window. */
    case Active = 'active';

    /** `expires_at` has passed; the account deactivates after the grace period. */
    case Expiring = 'expiring';

    /** `expires_at` plus the grace period has passed; the account is deactivated. */
    case Expired = 'expired';

    /** No expiry was ever set (e.g. non-students or an unknown year level). */
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Expiring => 'Expiring',
            self::Expired => 'Expired',
            self::None => 'No expiry',
        };
    }
}
