<?php

namespace App\Services\AccountManagement;

use InvalidArgumentException;

/** Validates that a typed command cannot be detached from its signed snapshot scope. */
final class CommandFreshness
{
    public static function forAccount(FreshnessSnapshot $snapshot, ActorAccountId $actor, ManagedAccountId $target): void
    {
        if ($snapshot->actorId !== $actor->value || $snapshot->targetId !== $target->value) {
            throw new InvalidArgumentException('Freshness data is not scoped to this account command.');
        }
    }

    public static function forAppointment(
        FreshnessSnapshot $snapshot,
        ActorAccountId $actor,
        ManagedAccountId $candidate,
        OrganizationId $organization,
        OrganizationPosition $position,
    ): void {
        self::forAccount($snapshot, $actor, $candidate);
        if ($snapshot->organization?->id !== $organization->value || $snapshot->slot?->organizationId !== $organization->value
            || $snapshot->slot->position !== $position->role()) {
            throw new InvalidArgumentException('Freshness data is not scoped to this organization position.');
        }
    }
}
