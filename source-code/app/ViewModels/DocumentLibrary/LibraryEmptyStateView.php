<?php

namespace App\ViewModels\DocumentLibrary;

final readonly class LibraryEmptyStateView
{
    /** @param list<RecoveryActionView> $actions */
    public function __construct(
        public string $kind,
        public string $title,
        public string $message,
        public array $actions,
    ) {}
}
