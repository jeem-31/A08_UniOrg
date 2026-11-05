<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ContentCache
{
    private const DEFAULT_TTL_MINUTES = 10;

    public function rememberEventIndex(?int $organizationId, ?string $userRole, int $page, Closure $callback): mixed
    {
        return $this->remember(
            'events',
            sprintf(
                'index:%s:role:%s:page:%d',
                $this->organizationScope($organizationId),
                Str::slug($userRole ?: 'none'),
                $page
            ),
            $callback
        );
    }

    public function rememberEventCalendar(Closure $callback): mixed
    {
        return $this->remember('events', 'calendar', $callback);
    }

    public function rememberEvent(int $eventId, Closure $callback): mixed
    {
        return $this->remember('events', "show:{$eventId}", $callback);
    }

    public function rememberAnnouncementIndex(?int $organizationId, int $page, Closure $callback): mixed
    {
        return $this->remember(
            'announcements',
            sprintf('index:%s:page:%d', $this->organizationScope($organizationId), $page),
            $callback
        );
    }

    public function rememberAnnouncement(int $announcementId, ?int $organizationId, Closure $callback): mixed
    {
        return $this->remember(
            'announcements',
            sprintf('show:%s:%d', $this->organizationScope($organizationId), $announcementId),
            $callback
        );
    }

    public function rememberLibraryResources(?int $organizationId, Closure $callback): mixed
    {
        return $this->remember(
            'library-resources',
            'index:'.$this->organizationScope($organizationId),
            $callback
        );
    }

    public function invalidateEvents(): void
    {
        $this->invalidate('events');
    }

    public function invalidateAnnouncements(): void
    {
        $this->invalidate('announcements');
    }

    public function invalidateLibraryResources(): void
    {
        $this->invalidate('library-resources');
    }

    private function remember(string $group, string $suffix, Closure $callback): mixed
    {
        return Cache::remember(
            $this->key($group, $suffix),
            now()->addMinutes($this->ttlMinutes()),
            $callback
        );
    }

    private function invalidate(string $group): void
    {
        $versionKey = $this->versionKey($group);

        Cache::add($versionKey, 1);
        Cache::increment($versionKey);
    }

    private function key(string $group, string $suffix): string
    {
        return sprintf(
            '%s:%s:v%d:%s',
            config('content-cache.prefix'),
            $group,
            $this->version($group),
            $suffix
        );
    }

    private function version(string $group): int
    {
        $versionKey = $this->versionKey($group);

        Cache::add($versionKey, 1);

        return (int) Cache::get($versionKey);
    }

    private function versionKey(string $group): string
    {
        return sprintf('%s:%s:version', config('content-cache.prefix'), $group);
    }

    private function organizationScope(?int $organizationId): string
    {
        return $organizationId === null ? 'global' : "organization:{$organizationId}";
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) config('content-cache.ttl_minutes', self::DEFAULT_TTL_MINUTES));
    }
}
