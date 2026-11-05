<?php

namespace App\ViewModels\DocumentLibrary;

final readonly class FolderView
{
    public function __construct(
        public string $type,
        public string $key,
        public string $label,
        public int $documentCount,
        public string $url,
        public ?string $logoUrl = null,
    ) {}
}
