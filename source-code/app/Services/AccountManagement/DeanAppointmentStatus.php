<?php

namespace App\Services\AccountManagement;

use App\Models\User;

/** The Dean_Appointment status of exactly one managed account. */
enum DeanAppointmentStatus: string
{
    /** Active `super_admin`: the single system-wide Dean_Appointment holder. */
    case CurrentDean = 'current_dean';

    /** Retains `super_admin` while inactive, so it holds no active appointment. */
    case InactiveDean = 'inactive_dean';

    case NotDean = 'not_dean';

    public static function forUser(User $user): self
    {
        if ($user->role !== 'super_admin') {
            return self::NotDean;
        }

        return $user->is_active ? self::CurrentDean : self::InactiveDean;
    }

    public function holdsAppointment(): bool
    {
        return $this === self::CurrentDean;
    }
}
