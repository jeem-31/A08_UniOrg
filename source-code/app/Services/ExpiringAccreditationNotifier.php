<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\AccreditationExpiringSoonNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpiringAccreditationNotifier
{
    /**
     * Notify Dean + Adviser when accreditation will expire within 30 days.
     * Based on OSAS Settings > Accreditation Expiration (global period).
     * Idempotent per org+expires_at: won't resend same days_left for same expiry.
     *
     * @return array{notified: int, skipped: int}
     */
    public function notify(): array
    {
        $expiringOrgs = Organization::where('status', 'Approved')
            ->whereNotNull('accreditation_expires_at')
            ->get()
            ->filter(fn (Organization $org) => $org->accreditationStatus() === 'expiring');

        $notified = 0;
        $skipped = 0;

        foreach ($expiringOrgs as $org) {
            $daysLeft = $org->daysUntilExpiration();
            if ($daysLeft === null || $daysLeft < 1 || $daysLeft > 30) {
                $skipped++;
                continue;
            }

            $recipients = $this->resolveDeanAndAdvisers($org);
            if ($recipients->isEmpty()) {
                $skipped++;
                continue;
            }

            // Idempotency: already notified for this org+expires_at+days_left?
            $expiresAtStr = $org->accreditation_expires_at?->toDateString();
            $alreadyNotified = false;
            foreach ($recipients as $recipient) {
                $exists = DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->where('notifiable_id', $recipient->id)
                    ->where('type', AccreditationExpiringSoonNotification::class)
                    ->whereRaw("data::text LIKE ?", ['%'.$expiresAtStr.'%'])
                    ->whereRaw("data::text LIKE ?", ['%"days_left":'.$daysLeft.'%'])
                    ->exists();
                // Fallback for SQLite / non-pgsql where data stored as text
                if (! $exists) {
                    $exists = $recipient->notifications()
                        ->where('type', AccreditationExpiringSoonNotification::class)
                        ->get()
                        ->contains(function ($n) use ($org, $expiresAtStr, $daysLeft) {
                            $data = is_string($n->data) ? json_decode($n->data, true) : $n->data;
                            return ($data['organization_id'] ?? null) == $org->id
                                && ($data['expires_at'] ?? null) == $expiresAtStr
                                && ($data['days_left'] ?? null) == $daysLeft;
                        });
                }
                if ($exists) {
                    $alreadyNotified = true;
                    break;
                }
            }

            if ($alreadyNotified) {
                $skipped++;
                continue;
            }

            foreach ($recipients as $recipient) {
                if (filter_var(trim((string) $recipient->email), FILTER_VALIDATE_EMAIL) === false) {
                    Log::warning('Skipping invalid email for expiring accreditation notification.', [
                        'organization_id' => $org->id,
                        'user_id' => $recipient->id,
                    ]);
                    continue;
                }
                try {
                    $recipient->notify(new AccreditationExpiringSoonNotification($org, $daysLeft));
                } catch (\Throwable $e) {
                    Log::warning('Failed expiring accreditation notification.', [
                        'organization_id' => $org->id,
                        'user_id' => $recipient->id,
                        'reason' => $e->getMessage(),
                    ]);
                }
            }

            $notified++;
        }

        return ['notified' => $notified, 'skipped' => $skipped];
    }

    /**
     * Resolve Dean + Adviser(s) for an organization via organization_user pivot.
     * Dean: role = DEAN, Adviser: role like %ADVISER% (includes Senior/Junior/Organization Adviser).
     */
    private function resolveDeanAndAdvisers(Organization $org)
    {
        return $org->users()
            ->wherePivot('membership_status', 'Active')
            ->where(function ($q) {
                $q->whereRaw('UPPER(organization_user.role) = ?', ['DEAN'])
                  ->orWhereRaw('UPPER(organization_user.role) LIKE ?', ['%ADVISER%'])
                  ->orWhereRaw('UPPER(organization_user.role) LIKE ?', ['%ADVISOR%']);
            })
            ->get()
            ->unique('id');
    }
}
