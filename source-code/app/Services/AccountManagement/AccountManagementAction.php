<?php

namespace App\Services\AccountManagement;

/** Completed action types the presentation layer may safely describe. */
enum AccountManagementAction: string
{
    case AccountCreated = 'account.created';
    case ProfileUpdated = 'profile.updated';
    case RoleChanged = 'role.changed';
    case AccountReactivated = 'account.reactivated';
    case AccountDeactivated = 'account.deactivated';
    case AccountExpired = 'account.expired';
    case AccountDeleted = 'account.deleted';
    case DeanAssigned = 'dean.assigned';
    case DeanReplaced = 'dean.replaced';
    case AdviserAppointed = 'adviser.appointed';
    case OfficerAppointed = 'officer.appointed';

    /** A non-sensitive label the audit interface can display for the action. */
    public function label(): string
    {
        return match ($this) {
            self::AccountCreated => 'Account created',
            self::ProfileUpdated => 'Profile updated',
            self::RoleChanged => 'Role changed',
            self::AccountReactivated => 'Account reactivated',
            self::AccountDeactivated => 'Account deactivated',
            self::AccountExpired => 'Account expired',
            self::AccountDeleted => 'Account permanently deleted',
            self::DeanAssigned => 'Dean assigned',
            self::DeanReplaced => 'Dean replaced',
            self::AdviserAppointed => 'Adviser appointed',
            self::OfficerAppointed => 'Officer appointed',
        };
    }

    public function isAppointment(): bool
    {
        return in_array($this, [self::AdviserAppointed, self::OfficerAppointed], true);
    }

    public function isDeanTransition(): bool
    {
        return in_array($this, [self::DeanAssigned, self::DeanReplaced], true);
    }
}
