<?php

namespace App\Policies;

use App\Models\User;

final class OsasAccountPolicy
{
    public const ABILITY = 'manage-osas-account-management';

    /**
     * Determine whether the actor may access OSAS account-management behavior.
     *
     * The actor is reloaded so queued/direct invocations cannot authorize using
     * a stale authenticated-user instance after its role or active state changes.
     */
    public function manage(User $actor): bool
    {
        $actorId = $actor->getAuthIdentifier();

        if ($actorId === null) {
            return false;
        }

        $currentActor = User::query()
            ->select(['id', 'role', 'is_active'])
            ->find($actorId);

        return $currentActor !== null
            && $currentActor->is_active
            && $currentActor->role === 'osas_admin';
    }
}
