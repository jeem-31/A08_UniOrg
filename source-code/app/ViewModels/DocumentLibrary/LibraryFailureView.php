<?php

namespace App\ViewModels\DocumentLibrary;

final readonly class LibraryFailureView
{
    /** @param list<RecoveryActionView> $actions */
    public function __construct(
        public string $kind,
        public string $title,
        public string $message,
        public array $actions,
        public ?string $retryUrl = null,
    ) {}
}
