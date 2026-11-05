<?php

namespace App\Services\AccountManagement;

/** Immutable, snapshot-bound ordinary role change that can never request super_admin. */
final readonly class ChangeNonDeanRoleCommand
{
    public function __construct(
        public ActorAccountId $actor,
        public ManagedAccountId $target,
        public AssignableNonDeanRole $role,
        public FreshnessSnapshot $freshness,
    ) {
        CommandFreshness::forAccount($freshness, $actor, $target);
    }
}
