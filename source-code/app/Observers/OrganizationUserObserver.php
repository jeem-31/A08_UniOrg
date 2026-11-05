<?php

namespace App\Observers;

use App\Models\OrganizationUser;
use App\Services\OrganizationPermissionService;

class OrganizationUserObserver
{
    public function __construct(
        private OrganizationPermissionService $permissionService
    ) {}

    /**
     * Handle a newly-created membership so a cached missing assignment cannot survive.
     */
    public function created(OrganizationUser $organizationUser): void
    {
        $this->invalidate($organizationUser);
    }

    /**
     * Handle the OrganizationUser "updated" event.
     * Invalidate cache when position or membership_status changes.
     */
    public function updated(OrganizationUser $organizationUser): void
    {
        if ($organizationUser->wasChanged('role') || $organizationUser->wasChanged('membership_status')) {
            $this->invalidate($organizationUser);
        }
    }

    /**
     * Handle the OrganizationUser "deleted" event.
     * Invalidate cache when membership is removed.
     */
    public function deleted(OrganizationUser $organizationUser): void
    {
        $this->invalidate($organizationUser);
    }

    private function invalidate(OrganizationUser $organizationUser): void
    {
        $this->permissionService->invalidateCache(
            $organizationUser->user_id,
            $organizationUser->organization_id
        );
    }
}
