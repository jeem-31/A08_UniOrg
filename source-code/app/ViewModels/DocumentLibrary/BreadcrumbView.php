<?php

namespace App\ViewModels\DocumentLibrary;

final readonly class BreadcrumbView
{
    public function __construct(
        public string $label,
        public ?string $url,
        public bool $current,
    ) {}
}
