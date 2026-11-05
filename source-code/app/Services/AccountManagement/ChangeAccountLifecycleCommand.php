<?php

namespace App\Services\AccountManagement;

use InvalidArgumentException;

/** Immutable activation/deactivation request whose actor and target are distinct. */
final readonly class ChangeAccountLifecycleCommand
{
    public function __construct(
        public ActorAccountId $actor,
        public ManagedAccountId $target,
        public AccountLifecycleAction $action,
        public FreshnessSnapshot $freshness,
    ) {
        if ($actor->value === $target->value) {
            throw new InvalidArgumentException('An account lifecycle command cannot target its actor.');
        }

        CommandFreshness::forAccount($freshness, $actor, $target);
    }
}
