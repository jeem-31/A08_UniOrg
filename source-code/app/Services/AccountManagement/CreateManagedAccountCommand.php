<?php

namespace App\Services\AccountManagement;

/** Immutable, allow-listed request to create an active non-Dean managed account. */
final readonly class CreateManagedAccountCommand
{
    public function __construct(
        public ActorAccountId $actor,
        public ManagedAccountProfile $profile,
        public NewAccountCredential $credential,
        public AssignableNonDeanRole $role,
    ) {}
}
