<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Event;
use App\Models\User;
use App\Notifications\VenueUseNoticeNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * FYI-only notifier: informs the dean(s) owning a venue's premises
 * whenever an event proposes/updates that venue. Never throws —
 * notification failures must not break the proposal flow.
 */
class VenuePremisesNotifier
{
    public static function notifyOwnerDeans(Event $event, string $stage): void
    {
        try {
            $ownerAcronym = Event::venueOwnerAcronym($event->venue);
            if (! $ownerAcronym) {
                return;
            }

            $event->loadMissing('organization');
            $deans = self::ownerDeans($ownerAcronym);
            if ($deans->isEmpty()) {
                return;
            }

            Notification::send($deans, new VenueUseNoticeNotification($event, $stage, $ownerAcronym));
        } catch (\Throwable $e) {
            Log::warning('VenuePremisesNotifier failed: ' . $e->getMessage(), [
                'event_id' => $event->id ?? null,
                'stage' => $stage,
            ]);
        }
    }

    /**
     * Active dean account(s) of the owning department (per-dept super_admin).
     */
    public static function ownerDeans(string $ownerAcronym): Collection
    {
        $departmentId = Department::whereRaw('UPPER(department_acronym) = ?', [strtoupper($ownerAcronym)])
            ->value('id');

        if (! $departmentId) {
            return collect();
        }

        // NOTE: where('is_active', true) compiles to `"is_active" = 1` on
        // PostgreSQL and throws 42883 (boolean = integer). Use DB::raw('TRUE')
        // like the rest of the codebase does.
        return User::where('role', 'super_admin')
            ->where('is_active', DB::raw('TRUE'))
            ->where('department_id', $departmentId)
            ->get();
    }
}
