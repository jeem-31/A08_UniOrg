<?php

namespace App\Services\DocumentLibrary;

use Illuminate\Database\Eloquent\Model;

final readonly class DocumentPage
{
    /**
     * @param  list<Model>  $entries
     * @param  list<int>  $entryIds
     * @param  array<string, string>  $pathParameters
     */
    public function __construct(
        public array $entries,
        public array $entryIds,
        public int $total,
        public int $firstResult,
        public int $lastResult,
        public int $currentPage,
        public int $perPage,
        public int $lastPage,
        public LibraryQuery $query,
        public array $pathParameters,
        public string $canonicalUrl,
        public ?string $previousPageUrl,
        public ?string $nextPageUrl,
        public string $firstPageUrl,
        public string $lastPageUrl,
    ) {}

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }
}
