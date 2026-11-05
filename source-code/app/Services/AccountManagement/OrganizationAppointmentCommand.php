<?php

namespace App\Services\AccountManagement;

/**
 * Shared immutable input for adviser and officer services. Factories prevent
 * callers from supplying a raw pivot role or a position from the wrong family.
 */
final readonly class OrganizationAppointmentCommand
{
    private function __construct(
        public ActorAccountId $actor,
        public OrganizationId $organization,
        public ManagedAccountId $candidate,
        public AdviserPosition|OfficerPosition $position,
        public FreshnessSnapshot $freshness,
    ) {
        CommandFreshness::forAppointment($freshness, $actor, $candidate, $organization, $position);
    }

    public static function forAdviser(
        ActorAccountId $actor,
        OrganizationId $organization,
        ManagedAccountId $candidate,
        AdviserPosition $position,
        FreshnessSnapshot $freshness,
    ): self {
        return new self($actor, $organization, $candidate, $position, $freshness);
    }

    public static function forOfficer(
        ActorAccountId $actor,
        OrganizationId $organization,
        ManagedAccountId $candidate,
        OfficerPosition $position,
        FreshnessSnapshot $freshness,
    ): self {
        return new self($actor, $organization, $candidate, $position, $freshness);
    }

    public function isAdviser(): bool
    {
        return $this->position instanceof AdviserPosition;
    }
}
