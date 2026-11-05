<?php

namespace App\Services\AccountManagement;

/**
 * Directory_Context: the managed-account directory search, filters, and page an
 * administrator navigated from.
 *
 * It wraps the canonical {@see AccountDirectoryFilter} so detail pages, not-found
 * results, isolation failures, and cancellation links all re-emit exactly the
 * directory state that was selected, never a widened or invented filter.
 */
final readonly class DirectoryContext
{
    private function __construct(public AccountDirectoryFilter $filter) {}

    public static function fromFilter(AccountDirectoryFilter $filter): self
    {
        return new self($filter);
    }

    /** @param array<string, mixed> $parameters Raw canonical `q`, `role`, `state`, `page` state. */
    public static function fromParameters(array $parameters): self
    {
        return new self(AccountDirectoryFilter::fromQuery($parameters));
    }

    public static function empty(): self
    {
        return new self(AccountDirectoryFilter::unfiltered());
    }

    /**
     * The canonical parameters, including page, for every directory return link.
     *
     * @return array<string, string>
     */
    public function queryParameters(): array
    {
        return $this->filter->toContextParameters();
    }

    public function search(): ?string
    {
        return $this->filter->search;
    }

    public function role(): ?SystemAccessRole
    {
        return $this->filter->role;
    }

    public function state(): ?AccountState
    {
        return $this->filter->state;
    }

    public function validity(): ?ValidityState
    {
        return $this->filter->validity;
    }

    public function page(): int
    {
        return $this->filter->page;
    }
}
