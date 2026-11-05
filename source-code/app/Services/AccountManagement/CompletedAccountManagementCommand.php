<?php

namespace App\Services\AccountManagement;

use InvalidArgumentException;

/** Immutable, scoped success result for any completed management command. */
final readonly class CompletedAccountManagementCommand implements AccountManagementCommandResult
{
    public function __construct(
        public AccountManagementAction $action,
        public ManagedAccountId $affectedAccount,
        public ?OrganizationId $organization = null,
        public AdviserPosition|OfficerPosition|null $position = null,
    ) {
        if ($action->isAppointment() !== ($organization !== null && $position !== null)) {
            throw new InvalidArgumentException('Appointment result scope does not match its action.');
        }
    }

    public function completed(): bool
    {
        return true;
    }
}
