<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('osas-proposals', static function (User $user): bool {
    return $user->role === 'osas_admin';
});

Broadcast::channel('membership-fee.{userId}', static function (User $user, int $userId): bool {
    return $user->getKey() === $userId;
});

Broadcast::channel('student-payments.{userId}', static function (User $user, int $userId): bool {
    return $user->getKey() === $userId;
});

Broadcast::channel('organization-payments.{organizationId}', static function (User $user, int $organizationId): bool {
    if (in_array($user->role, ['super_admin', 'osas_admin'], true)) {
        return true;
    }

    return app(\App\Services\OrganizationPermissionService::class)
        ->hasAccess($user->getKey(), $organizationId, 'payments', 'read');
});
