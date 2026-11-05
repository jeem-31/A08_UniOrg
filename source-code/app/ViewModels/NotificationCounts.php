<?php

namespace App\ViewModels;

use InvalidArgumentException;

final readonly class NotificationCounts
{
    public function __construct(
        public int $total,
        public int $unread,
    ) {
        if ($total < 0 || $unread < 0 || $unread > $total) {
            throw new InvalidArgumentException(
                'Notification counts must satisfy 0 <= unread <= total.',
            );
        }
    }
}
