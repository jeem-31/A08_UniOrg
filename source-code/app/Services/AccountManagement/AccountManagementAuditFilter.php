<?php

namespace App\Services\AccountManagement;

use InvalidArgumentException;

/**
 * The canonical, immutable audit filter state.
 *
 * Every selected filter is AND-composed by the query; an absent filter never
 * broadens another one, and the filter itself carries no credential data.
 */
final readonly class AccountManagementAuditFilter
{
    public const PARAM_ACTION = 'action';

    public const PARAM_ACTOR = 'actor';

    public const PARAM_ACCOUNT = 'account';

    public const PARAM_ORGANIZATION = 'organization';

    public const PARAM_FROM = 'from';

    public const PARAM_TO = 'to';

    public const PARAM_PAGE = 'page';

    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    public function __construct(
        public ?AccountManagementAction $action = null,
        public ?ActorAccountId $actor = null,
        public ?ManagedAccountId $affectedAccount = null,
        public ?OrganizationId $organization = null,
        public ?AuditTimeRange $timeRange = null,
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException('An audit page number must be positive.');
        }

        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new InvalidArgumentException('An audit page size must be between 1 and '.self::MAX_PER_PAGE.'.');
        }
    }

    public static function none(): self
    {
        return new self;
    }

    /** Retains every other selected filter while dropping a rejected time range. */
    public function withoutTimeRange(): self
    {
        return new self(
            $this->action,
            $this->actor,
            $this->affectedAccount,
            $this->organization,
            null,
            $this->page,
            $this->perPage,
        );
    }

    public function onPage(int $page): self
    {
        return new self(
            $this->action,
            $this->actor,
            $this->affectedAccount,
            $this->organization,
            $this->timeRange,
            $page,
            $this->perPage,
        );
    }

    public function hasSelectedFilters(): bool
    {
        return $this->action !== null
            || $this->actor !== null
            || $this->affectedAccount !== null
            || $this->organization !== null
            || ($this->timeRange !== null && ! $this->timeRange->isEmpty());
    }

    /**
     * Canonical query parameters so pagination and recovery links retain filters.
     *
     * @return array<string, string>
     */
    public function toQueryParameters(): array
    {
        $parameters = [];

        if ($this->action !== null) {
            $parameters[self::PARAM_ACTION] = $this->action->value;
        }

        if ($this->actor !== null) {
            $parameters[self::PARAM_ACTOR] = (string) $this->actor->value;
        }

        if ($this->affectedAccount !== null) {
            $parameters[self::PARAM_ACCOUNT] = (string) $this->affectedAccount->value;
        }

        if ($this->organization !== null) {
            $parameters[self::PARAM_ORGANIZATION] = (string) $this->organization->value;
        }

        if ($this->timeRange?->start !== null) {
            $parameters[self::PARAM_FROM] = $this->timeRange->start->format('Y-m-d\TH:i');
        }

        if ($this->timeRange?->end !== null) {
            $parameters[self::PARAM_TO] = $this->timeRange->end->format('Y-m-d\TH:i');
        }

        if ($this->page > 1) {
            $parameters[self::PARAM_PAGE] = (string) $this->page;
        }

        return $parameters;
    }
}
