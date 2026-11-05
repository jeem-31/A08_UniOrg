<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PermissionAuditLogger
{
    /**
     * Log an access attempt to the permissions log channel.
     *
     * @param int $userId The ID of the user attempting access
     * @param int $organizationId The ID of the organization context
     * @param string $module The module being accessed (announcements, events, etc.)
     * @param string $accessLevel The required access level (full, read)
     * @param bool $granted Whether access was granted or denied
     * @param string|null $position The user's organizational position (optional)
     * @return void
     */
    public function log(
        int $userId,
        int $organizationId,
        string $module,
        string $accessLevel,
        bool $granted,
        ?string $position = null
    ): void {
        $context = [
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'module' => $module,
            'access_level' => $accessLevel,
            'result' => $granted ? 'granted' : 'denied',
            'timestamp' => now()->toIso8601String(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        // Include position if provided (for requirement 14.3)
        if ($position !== null) {
            $context['position'] = $position;
        }

        Log::channel('permissions')->info('Access attempt', $context);
    }
}
