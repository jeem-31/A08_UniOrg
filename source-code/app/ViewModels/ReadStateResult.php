<?php

namespace App\ViewModels;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final readonly class ReadStateResult
{
    private function __construct(
        public bool $success,
        public ?CarbonImmutable $readAt,
        public int $unreadCount,
    ) {}

    public static function completed(
        ?DateTimeInterface $readAt,
        NotificationCounts $counts,
    ): self {
        return new self(
            success: true,
            readAt: $readAt === null ? null : CarbonImmutable::instance($readAt),
            unreadCount: $counts->unread,
        );
    }

    public static function notFound(NotificationCounts $counts): self
    {
        return new self(
            success: false,
            readAt: null,
            unreadCount: $counts->unread,
        );
    }
}
