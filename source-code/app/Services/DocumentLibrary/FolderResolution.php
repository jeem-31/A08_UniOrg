<?php

namespace App\Services\DocumentLibrary;

final readonly class FolderResolution
{
    /**
     * @param list<FolderBreadcrumb> $breadcrumbs
     */
    public function __construct(
        public string $level,
        public array $breadcrumbs,
        public ?ResolvedOrganizationFolder $organization,
        public AccreditationYear|UnspecifiedYear|null $year,
        public ?string $parentUrl,
        public string $rootUrl,
        public ?string $error,
    ) {}

    public function found(): bool
    {
        return $this->error === null;
    }

    public function heading(): string
    {
        return match ($this->level) {
            'organization' => $this->organization?->label ?? 'Document Library',
            'year' => $this->year?->label() ?? 'Document Library',
            default => 'Document Library',
        };
    }
}
