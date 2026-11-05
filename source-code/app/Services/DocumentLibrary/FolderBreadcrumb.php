<?php

namespace App\Services\DocumentLibrary;

final readonly class FolderBreadcrumb
{
    public function __construct(
        public string $label,
        public ?string $url,
        public bool $current,
    ) {}
}
