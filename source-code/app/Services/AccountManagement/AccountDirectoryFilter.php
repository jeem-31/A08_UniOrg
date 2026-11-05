<?php

namespace App\Services\AccountManagement;

/**
 * The canonical managed-account directory state: `q`, `role`, `state`, `page`.
 *
 * This is the single parse of directory GET state; {@see DirectoryContext} wraps
 * it so detail pages and recovery links re-emit exactly what was selected.
 * Parsing is strict and lossy on purpose: a value that is not an exact canonical
 * role or state is dropped rather than guessed, so a directory request can never
 * silently widen, narrow, or invent a filter the administrator did not select.
 */
final readonly class AccountDirectoryFilter
{
    public const PER_PAGE = 15;

    public const MAX_SEARCH_LENGTH = 255;

    private function __construct(
        public ?string $search,
        public ?SystemAccessRole $role,
        public ?AccountState $state,
        public ?ValidityState $validity,
        public ?int $organizationId,
        public int $page,
        public ?SystemAccessRole $excludeRole = null,
    ) {}

    public static function unfiltered(): self
    {
        return new self(null, null, null, null, null, 1);
    }

    /**
     * Build the filter from raw request query parameters.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function fromQuery(array $parameters): self
    {
        return new self(
            self::normalizeSearch($parameters['q'] ?? null),
            self::normalizeRole($parameters['role'] ?? null),
            self::normalizeState($parameters['state'] ?? null),
            self::normalizeValidity($parameters['validity'] ?? null),
            self::normalizeOrganization($parameters['organization'] ?? null),
            self::normalizePage($parameters['page'] ?? null),
        );
    }

    /** The directory state a detail, cancellation, or recovery link must carry. */
    public function toContext(): DirectoryContext
    {
        return DirectoryContext::fromFilter($this);
    }

    public function hasSearch(): bool
    {
        return $this->search !== null;
    }

    public function hasFilters(): bool
    {
        return $this->search !== null || $this->role !== null || $this->state !== null || $this->validity !== null || $this->organizationId !== null;
    }

    public function withPage(int $page): self
    {
        return new self($this->search, $this->role, $this->state, $this->validity, $this->organizationId, max(1, $page), $this->excludeRole);
    }

    /**
     * Exclude one role from the directory (e.g. hide Deans from the
     * normal-accounts table). Never emitted into query parameters.
     */
    public function withExcludedRole(?SystemAccessRole $role): self
    {
        return new self($this->search, $this->role, $this->state, $this->validity, $this->organizationId, $this->page, $role);
    }

    /**
     * The canonical parameters every directory link must carry. The current page
     * is excluded so paginator link building can own it.
     *
     * @return array<string, string>
     */
    public function toQueryParameters(): array
    {
        $parameters = [];

        if ($this->search !== null) {
            $parameters['q'] = $this->search;
        }
        if ($this->role !== null) {
            $parameters['role'] = $this->role->value;
        }
        if ($this->state !== null) {
            $parameters['state'] = $this->state->value;
        }
        if ($this->validity !== null) {
            $parameters['validity'] = $this->validity->value;
        }
        if ($this->organizationId !== null) {
            $parameters['organization'] = (string) $this->organizationId;
        }

        return $parameters;
    }

    /**
     * The canonical parameters including the current page, for detail links and
     * not-found or isolation-failure recovery.
     *
     * @return array<string, string>
     */
    public function toContextParameters(): array
    {
        $parameters = $this->toQueryParameters();

        if ($this->page > 1) {
            $parameters['page'] = (string) $this->page;
        }

        return $parameters;
    }

    private static function normalizeSearch(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, self::MAX_SEARCH_LENGTH, 'UTF-8');
    }

    private static function normalizeRole(mixed $value): ?SystemAccessRole
    {
        return is_string($value) ? SystemAccessRole::tryFrom($value) : null;
    }

    private static function normalizeState(mixed $value): ?AccountState
    {
        return is_string($value) ? AccountState::tryFrom($value) : null;
    }

    private static function normalizeValidity(mixed $value): ?ValidityState
    {
        return is_string($value) ? ValidityState::tryFrom($value) : null;
    }

    private static function normalizeOrganization(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9]\d*$/D', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private static function normalizePage(mixed $value): int
    {
        if (is_int($value)) {
            return max(1, $value);
        }
        if (is_string($value) && preg_match('/^[1-9]\d*$/D', $value) === 1) {
            return (int) $value;
        }

        return 1;
    }
}
