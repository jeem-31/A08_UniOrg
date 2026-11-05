<?php

namespace App\ViewModels\DocumentLibrary;

final readonly class LibraryPageView
{
    /**
     * @param list<BreadcrumbView> $breadcrumbs
     * @param list<DocumentEntryView> $documents
     * @param array<string, string|int|list<string>> $queryParameters
     * @param array<string, list<string>> $validationMessages
     * @param list<FolderView> $folders
     */
    public function __construct(
        public string $heading,
        public array $breadcrumbs,
        public ?string $parentUrl,
        public string $rootUrl,
        public array $documents,
        public array $queryParameters,
        public PaginationView $pagination,
        public string $canonicalUrl,
        public bool $detailsCollapsedByDefault = true,
        public array $validationMessages = [],
        public ?LibraryEmptyStateView $emptyState = null,
        public ?LibraryFailureView $failure = null,
        public string $level = 'root',
        public array $folders = [],
    ) {}
}
