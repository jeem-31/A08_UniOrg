<?php

namespace App\ViewModels\DocumentLibrary;

final readonly class PaginationView
{
    public function __construct(
        public int $total,
        public int $firstResult,
        public int $lastResult,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public ?string $previousUrl,
        public ?string $nextUrl,
        public string $firstUrl,
        public string $lastUrl,
    ) {}
}
