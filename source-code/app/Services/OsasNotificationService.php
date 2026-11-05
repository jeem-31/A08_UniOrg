<?php

namespace App\Services;

use App\Models\User;
use App\ViewModels\NotificationCounts;
use App\ViewModels\ReadStateResult;
use App\ViewModels\ResolvedNotificationItem;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class OsasNotificationService
{
    private const RECENT_LIMIT = 10;

    private const PAGE_LIMIT = 20;

    public function __construct(
        private OsasNotificationDestinationResolver $resolver,
        private UrlGenerator $urlGenerator,
    ) {}

    /** @return Collection<int, ResolvedNotificationItem> */
    public function recent(User $user, Request $request, int $limit = self::RECENT_LIMIT): Collection
    {
        return $user->notifications()
            ->orderByDesc('created_at')
            ->limit($this->bounded($limit, self::RECENT_LIMIT))
            ->get()
            ->map(fn (DatabaseNotification $notification): ResolvedNotificationItem => $this->resolveItem($notification, $request));
    }

    /** @return LengthAwarePaginator<int, ResolvedNotificationItem> */
    public function paginate(User $user, Request $request, int $perPage = self::PAGE_LIMIT): LengthAwarePaginator
    {
        $paginator = $user->notifications()
            ->orderByDesc('created_at')
            ->paginate($this->bounded($perPage, self::PAGE_LIMIT));

        return $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (DatabaseNotification $notification): ResolvedNotificationItem => $this->resolveItem($notification, $request),
            ),
        );
    }

    public function counts(User $user): NotificationCounts
    {
        $aggregate = $user->notifications()
            ->reorder()
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw('COUNT(CASE WHEN read_at IS NULL THEN 1 END) AS unread_count')
            ->first();

        return new NotificationCounts(
            total: (int) $aggregate?->getAttribute('total_count'),
            unread: (int) $aggregate?->getAttribute('unread_count'),
        );
    }

    /** @return array{unread_count: int, dropdown_html: string} */
    public function realtimeState(User $user, Request $request): array
    {
        $counts = $this->counts($user);

        return [
            'unread_count' => $counts->unread,
            'dropdown_html' => view('components.osas.notification-dropdown-content', [
                'unreadCount' => $counts->unread,
                'notifications' => $this->recent($user, $request),
            ])->render(),
        ];
    }

    public function markRead(User $user, string $id): ReadStateResult
    {
        return DB::transaction(function () use ($user, $id): ReadStateResult {
            $notification = $user->notifications()
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (! $notification instanceof DatabaseNotification) {
                return ReadStateResult::notFound($this->counts($user));
            }

            if ($notification->read_at === null) {
                $notification->markAsRead();
            }

            return ReadStateResult::completed(
                $notification->read_at,
                $this->counts($user),
            );
        });
    }

    public function markAllRead(User $user): ReadStateResult
    {
        return DB::transaction(function () use ($user): ReadStateResult {
            $readAt = now()->toImmutable();

            $user->unreadNotifications()->update(['read_at' => $readAt]);

            return ReadStateResult::completed($readAt, $this->counts($user));
        });
    }

    private function resolveItem(
        DatabaseNotification $notification,
        Request $request,
    ): ResolvedNotificationItem {
        return ResolvedNotificationItem::fromNotification(
            notification: $notification,
            destination: $this->resolver->resolve($notification, $request),
            readUrl: $this->urlGenerator->route(
                'osas.notifications.mark-read',
                ['notification' => (string) $notification->getKey()],
            ),
        );
    }

    private function bounded(int $requested, int $maximum): int
    {
        return max(1, min($requested, $maximum));
    }
}
