<?php

namespace App\Services\AccountManagement;

/** Immutable, snapshot-bound patch of only explicitly whitelisted profile fields. */
final readonly class UpdateManagedAccountProfileCommand
{
    public function __construct(
        public ActorAccountId $actor,
        public ManagedAccountId $target,
        public ManagedAccountProfile $profile,
        public FreshnessSnapshot $freshness,
    ) {
        CommandFreshness::forAccount($freshness, $actor, $target);
    }
}
