<?php

namespace App\Services\AccountManagement;

use InvalidArgumentException;

/** An immutable newest-first page of credential-free audit projections. */
final readonly class AuditHistoryPage implements AuditBrowseResult
{
    /** @param list<AuditEntryView> $entries */
    public function __construct(
        private AccountManagementAuditFilter $appliedFilter,
        public array $entries,
        public int $total,
    ) {
        if ($total < 0) {
            throw new InvalidArgumentException('An audit page total cannot be negative.');
        }
    }

    public function accepted(): bool
    {
        return true;
    }

    public function filter(): AccountManagementAuditFilter
    {
        return $this->appliedFilter;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function page(): int
    {
        return $this->appliedFilter->page;
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->appliedFilter->perPage));
    }

    public function hasPreviousPage(): bool
    {
        return $this->page() > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->page() < $this->lastPage();
    }

    /** @return array<string, string> */
    public function queryParametersForPage(int $page): array
    {
        return $this->appliedFilter->onPage($page)->toQueryParameters();
    }
}
