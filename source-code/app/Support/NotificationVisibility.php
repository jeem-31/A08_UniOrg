<?php

namespace App\Support;

use App\Models\Complaint;
use App\Models\Election;
use App\Models\EdushareFolder;
use App\Models\EdushareItem;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\StudentPayment;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

final class NotificationVisibility
{
    /**
     * Hide organization-scoped notifications from a different active org.
     * Non-Academic notifications remain visible because those broadcasts are
     * intentionally campus-wide.
     */
    public static function forUser(User $user, Collection $notifications): Collection
    {
        if (!$user->isEffectivelyStudent()) {
            return $notifications;
        }

        $currentOrganizationId = self::currentOrganizationId($user);

        if (!$currentOrganizationId) {
            return $notifications;
        }

        $sourceOrganizationIds = self::sourceOrganizationIds($notifications);
        $organizationIds = collect($sourceOrganizationIds)
            ->filter(fn ($organizationId): bool => !is_null($organizationId))
            ->map(fn ($organizationId): int => (int) $organizationId)
            ->unique()
            ->values();
        $organizations = $organizationIds->isEmpty()
            ? collect()
            : Organization::whereIn('id', $organizationIds)->get()->keyBy('id');

        return $notifications->filter(function (DatabaseNotification $notification) use (
            $currentOrganizationId,
            $sourceOrganizationIds,
            $organizations
        ): bool {
            $sourceOrganizationId = $sourceOrganizationIds[$notification->id] ?? null;

            // Keep unrelated/system notifications visible. They do not carry
            // an organization context and are not safe to infer from text.
            if (is_null($sourceOrganizationId)) {
                return true;
            }

            $sourceOrganization = $organizations->get((int) $sourceOrganizationId);

            return $sourceOrganization?->isNonAcademic()
                || (int) $sourceOrganizationId === $currentOrganizationId;
        })->values();
    }

    private static function currentOrganizationId(User $user): ?int
    {
        $organizationId = session('current_organization_id');

        if ($organizationId) {
            return (int) $organizationId;
        }

        return $user->activeOrganizations()->value('organizations.id');
    }

    /**
     * Resolve the organization for new notifications from data and for old
     * rows that predate organization_id in the notification payload.
     *
     * @return array<string, int|null>
     */
    private static function sourceOrganizationIds(Collection $notifications): array
    {
        $resolved = [];
        $legacyKeysByNotification = [];
        $legacyIds = [
            'complaint' => [],
            'election' => [],
            'edushare_folder' => [],
            'edushare_item' => [],
            'event' => [],
            'payment' => [],
            'student_payment' => [],
        ];

        foreach ($notifications as $notification) {
            $data = is_array($notification->data) ? $notification->data : [];

            if (array_key_exists('organization_id', $data) && $data['organization_id']) {
                $resolved[$notification->id] = (int) $data['organization_id'];
                continue;
            }

            $legacyKey = self::legacyKey($notification, $data);

            if (!$legacyKey) {
                continue;
            }

            [$resourceType, $resourceId] = $legacyKey;
            $key = $resourceType . ':' . $resourceId;
            $legacyKeysByNotification[$notification->id] = [$key];
            $legacyIds[$resourceType][$resourceId] = $resourceId;

            // Older payment notifications used both Payment and
            // StudentPayment records under the same payload key.
            if ($resourceType === 'payment') {
                $studentPaymentKey = 'student_payment:' . $resourceId;
                $legacyKeysByNotification[$notification->id][] = $studentPaymentKey;
                $legacyIds['student_payment'][$resourceId] = $resourceId;
            }
        }

        $organizationByLegacyKey = [];
        foreach ($legacyIds as $resourceType => $ids) {
            if ($ids === []) {
                continue;
            }

            $model = match ($resourceType) {
                'complaint' => Complaint::class,
                'election' => Election::class,
                'edushare_folder' => EdushareFolder::class,
                'edushare_item' => EdushareItem::class,
                'event' => Event::class,
                'payment' => Payment::class,
                'student_payment' => StudentPayment::class,
            };

            $records = $model::withoutGlobalScopes()
                ->whereIn('id', array_values($ids))
                ->get(['id', 'organization_id']);

            foreach ($records as $record) {
                $organizationByLegacyKey[$resourceType . ':' . $record->id] = $record->organization_id
                    ? (int) $record->organization_id
                    : null;
            }
        }

        foreach ($legacyKeysByNotification as $notificationId => $legacyKeys) {
            foreach ($legacyKeys as $legacyKey) {
                if (array_key_exists($legacyKey, $organizationByLegacyKey)) {
                    $resolved[$notificationId] = $organizationByLegacyKey[$legacyKey];
                    break;
                }
            }
        }

        return $resolved;
    }

    private static function legacyKey(DatabaseNotification $notification, array $data): ?array
    {
        $notificationType = (string) $notification->type;

        if (isset($data['election_id'])) {
            return ['election', (int) $data['election_id']];
        }

        if (isset($data['complaint_id'])) {
            return ['complaint', (int) $data['complaint_id']];
        }

        if (isset($data['event_id'])) {
            return ['event', (int) $data['event_id']];
        }

        if (isset($data['item_id']) && str_contains($notificationType, 'Edushare')) {
            return ['edushare_item', (int) $data['item_id']];
        }

        if (isset($data['folder_id']) && str_contains($notificationType, 'Edushare')) {
            return ['edushare_folder', (int) $data['folder_id']];
        }

        if (isset($data['payment_id'])) {
            return ['payment', (int) $data['payment_id']];
        }

        return null;
    }
}
