<?php

namespace App\Services\AccountManagement;

use InvalidArgumentException;

/** Immutable, snapshot-bound deletion request with an opaque confirmation token. */
final readonly class DeleteManagedAccountCommand
{
    public function __construct(
        public ActorAccountId $actor,
        public ManagedAccountId $target,
        public FreshnessSnapshot $freshness,
        public DestructiveConfirmationToken $confirmation,
    ) {
        if ($actor->value === $target->value) {
            throw new InvalidArgumentException('An account deletion command cannot target its actor.');
        }

        CommandFreshness::forAccount($freshness, $actor, $target);
    }
}
