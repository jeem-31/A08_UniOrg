<?php

namespace App\Services\AccountManagement;

/** Immutable Dean appointment or replacement request; replacement facts stay typed. */
final readonly class DeanAppointmentCommand
{
    public function __construct(
        public ActorAccountId $actor,
        public ManagedAccountId $candidate,
        public FreshnessSnapshot $freshness,
        public ?NonDeanOutcome $formerDeanOutcome = null,
        public ?DestructiveConfirmationToken $confirmation = null,
    ) {
        CommandFreshness::forAccount($freshness, $actor, $candidate);
    }
}
