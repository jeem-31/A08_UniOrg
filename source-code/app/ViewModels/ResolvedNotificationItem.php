<?php

namespace App\ViewModels;

use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;

final readonly class ResolvedNotificationItem
{
    private const ALLOWED_ICONS = [
        'ph-bell',
        'ph-megaphone',
        'ph-calendar-blank',
        'ph-certificate',
        'ph-check-circle',
        'ph-chats',
        'ph-file-text',
        'ph-folder',
        'ph-info',
        'ph-paper-plane-tilt',
        'ph-arrow-clockwise',
        'ph-x-circle',
        'ph-trophy',
        'ph-check-square',
        'ph-shield-check',
        'ph-user-minus',
    ];

    /**
     * Keep database notifications created before the Phosphor migration visible
     * without rewriting persisted notification payloads.
     */
    private const LEGACY_ICON_ALIASES = [
        'fa-bell' => 'ph-bell',
        'fa-bullhorn' => 'ph-megaphone',
        'fa-calendar-alt' => 'ph-calendar-blank',
        'fa-certificate' => 'ph-certificate',
        'fa-check-circle' => 'ph-check-circle',
        'fa-comments' => 'ph-chats',
        'fa-file-alt' => 'ph-file-text',
        'fa-folder' => 'ph-folder',
        'fa-info-circle' => 'ph-info',
        'fa-paper-plane' => 'ph-paper-plane-tilt',
        'fa-redo' => 'ph-arrow-clockwise',
        'fa-times-circle' => 'ph-x-circle',
        'fa-trophy' => 'ph-trophy',
        'fa-user-minus' => 'ph-user-minus',
        'fa-user-shield' => 'ph-shield-check',
        'fa-vote-yea' => 'ph-check-square',
    ];

    private const COLOR_CLASSES = [
        'amber' => 'bg-amber-50 text-amber-600',
        'blue' => 'bg-blue-50 text-blue-600',
        'gray' => 'bg-gray-50 text-gray-600',
        'green' => 'bg-green-50 text-green-600',
        'purple' => 'bg-purple-50 text-purple-600',
        'red' => 'bg-red-50 text-red-600',
    ];

    private function __construct(
        public string $id,
        public string $title,
        public string $message,
        public string $icon,
        public string $colorClass,
        public string $destination,
        public string $readUrl,
        public bool $isRead,
        public CarbonImmutable $createdAt,
        public string $createdLabel,
    ) {}

    public static function fromNotification(
        DatabaseNotification $notification,
        string $destination,
        string $readUrl,
    ): self {
        $data = is_array($notification->data) ? $notification->data : [];
        $createdAt = $notification->created_at === null
            ? CarbonImmutable::now()
            : CarbonImmutable::instance($notification->created_at);

        return new self(
            id: (string) $notification->getKey(),
            title: self::title($data),
            message: self::text($data['message'] ?? null, 'New notification'),
            icon: self::resolveIcon($data['icon'] ?? null),
            colorClass: self::colorClass($data['color'] ?? null),
            destination: $destination,
            readUrl: $readUrl,
            isRead: $notification->read_at !== null,
            createdAt: $createdAt,
            createdLabel: $createdAt->diffForHumans(),
        );
    }

    /** @param array<string, mixed> $data */
    private static function title(array $data): string
    {
        if (isset($data['title'])) {
            return self::text($data['title'], 'OSAS Notification');
        }

        return match ($data['type'] ?? null) {
            'renew' => 'Renewal Request',
            'apply' => 'Recognition Request',
            default => 'OSAS Notification',
        };
    }

    public static function resolveIcon(mixed $icon): string
    {
        if (! is_string($icon)) {
            return 'ph-bell';
        }

        $resolvedIcon = self::LEGACY_ICON_ALIASES[$icon] ?? $icon;

        return in_array($resolvedIcon, self::ALLOWED_ICONS, true)
            ? $resolvedIcon
            : 'ph-bell';
    }

    private static function colorClass(mixed $color): string
    {
        return is_string($color) && isset(self::COLOR_CLASSES[$color])
            ? self::COLOR_CLASSES[$color]
            : self::COLOR_CLASSES['blue'];
    }

    private static function text(mixed $value, string $fallback): string
    {
        return is_string($value) && trim($value) !== '' ? $value : $fallback;
    }
}
