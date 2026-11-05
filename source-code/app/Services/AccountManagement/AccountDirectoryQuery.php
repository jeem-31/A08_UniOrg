<?php

namespace App\Services\AccountManagement;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Read-only managed-account directory retrieval.
 *
 * The search value is matched literally: it is lowercased and its LIKE wildcards
 * are escaped, so `%` and `_` can only ever match themselves. Search, role, and
 * state predicates are AND-composed, and only the filters actually selected are
 * applied. No join is used, so a multi-assignment account cannot appear twice,
 * and only that account's own active assignment summaries are loaded.
 */
final class AccountDirectoryQuery
{
    /** Non-credential directory projection. `password` is never selected. */
    private const DIRECTORY_COLUMNS = [
        'users.id',
        'users.first_name',
        'users.middle_name',
        'users.middle_initial',
        'users.last_name',
        'users.suffix',
        'users.email',
        'users.student_id',
        'users.role',
        'users.is_active',
        'users.expires_at',
        'users.management_version',
    ];

    private const ASSIGNMENT_COLUMNS = [
        'organization_user.id',
        'organization_user.user_id',
        'organization_user.organization_id',
        'organization_user.role',
        'organization_user.membership_status',
    ];

    private const ORGANIZATION_COLUMNS = [
        'organizations.id',
        'organizations.name',
        'organizations.org_acronym',
    ];

    /** @return LengthAwarePaginator<User> */
    public function paginate(AccountDirectoryFilter $filter): LengthAwarePaginator
    {
        $accounts = $this->filtered($filter);

        return $accounts
            // A total order keeps page boundaries stable across identical names.
            ->orderByRaw($this->sortableText($accounts->getQuery(), 'users.last_name').' asc')
            ->orderByRaw($this->sortableText($accounts->getQuery(), 'users.first_name').' asc')
            ->orderBy('users.id')
            ->paginate(
                perPage: AccountDirectoryFilter::PER_PAGE,
                page: $filter->page,
            )
            ->appends($filter->toQueryParameters());
    }

    /**
     * The filtered directory relation, exposed for counting and reuse without
     * duplicating predicate composition.
     *
     * @return Builder<User>
     */
    public function filtered(AccountDirectoryFilter $filter): Builder
    {
        $accounts = User::query()
            ->select(self::DIRECTORY_COLUMNS)
            ->with([
                'activeOrganizationAssignments' => function (HasMany $assignments): void {
                    $assignments
                        ->select(self::ASSIGNMENT_COLUMNS)
                        ->orderBy('organization_user.organization_id')
                        ->orderBy('organization_user.id')
                        ->with([
                            'organization' => function (BelongsTo $organization): void {
                                $organization->select(self::ORGANIZATION_COLUMNS);
                            },
                        ]);
                },
            ]);

        $this->applySearch($accounts, $filter->search);
        $this->applyRole($accounts, $filter->role);
        $this->applyExcludedRole($accounts, $filter->excludeRole);
        $this->applyState($accounts, $filter->state);
        $this->applyValidity($accounts, $filter->validity);
        $this->applyOrganization($accounts, $filter->organizationId);

        return $accounts;
    }

    /** @param  Builder<User>  $accounts */
    private function applySearch(Builder $accounts, ?string $search): void
    {
        if ($search === null) {
            return;
        }

        $pattern = '%'.$this->escapeLikeLiteral($this->lower($search)).'%';

        $accounts->where(function (Builder $matches) use ($pattern): void {
            $query = $matches->getQuery();
            $expressions = [
                $this->fullNameExpression($query),
                $this->searchableText($query, 'users.email'),
                $this->searchableText($query, 'users.student_id'),
            ];

            foreach ($expressions as $index => $expression) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $matches->{$method}($expression." LIKE ? ESCAPE '!'", [$pattern]);
            }
        });
    }

    /** @param  Builder<User>  $accounts */
    private function applyRole(Builder $accounts, ?SystemAccessRole $role): void
    {
        if ($role !== null) {
            $accounts->where('users.role', $role->value);
        }
    }

    /** @param  Builder<User>  $accounts */
    private function applyExcludedRole(Builder $accounts, ?SystemAccessRole $role): void
    {
        if ($role !== null) {
            $accounts->where('users.role', '!=', $role->value);
        }
    }

    /** @param  Builder<User>  $accounts */
    private function applyState(Builder $accounts, ?AccountState $state): void
    {
        if ($state !== null) {
            $accounts->whereRaw('CAST(users.is_active AS INTEGER) = ?', [$state->isActive() ? 1 : 0]);
        }
    }

    /** @param  Builder<User>  $accounts */
    private function applyOrganization(Builder $accounts, ?int $organizationId): void
    {
        if ($organizationId === null) {
            return;
        }

        $accounts->whereHas('activeOrganizationAssignments', function (Builder $q) use ($organizationId): void {
            $q->where('organization_user.organization_id', $organizationId);
        });
    }

    /**
     * Apply the fixed-window validity filter. The two-month grace closes after
     * `expires_at`, so the boundaries are expressed as plain now()-based cutoffs
     * to stay database-agnostic.
     *
     * @param  Builder<User>  $accounts
     */
    private function applyValidity(Builder $accounts, ?ValidityState $validity): void
    {
        if ($validity === null) {
            return;
        }

        $now = now(config('app.timezone'));
        $graceCutoff = $now->copy()->subMonths(StudentAccountValidity::GRACE_MONTHS);

        switch ($validity) {
            case ValidityState::Active:
                $accounts->where(function (Builder $valid) use ($now): void {
                    $valid->whereNull('users.expires_at')
                        ->orWhere('users.expires_at', '>', $now);
                });
                break;

            case ValidityState::Expiring:
                $accounts->whereNotNull('users.expires_at')
                    ->where('users.expires_at', '<=', $now)
                    ->where('users.expires_at', '>', $graceCutoff);
                break;

            case ValidityState::Expired:
                $accounts->whereNotNull('users.expires_at')
                    ->where('users.expires_at', '<=', $graceCutoff);
                break;

            case ValidityState::None:
                $accounts->whereNull('users.expires_at');
                break;
        }
    }

    /**
     * Reproduce the displayed full name inside SQL so a search value spanning
     * name parts matches exactly the name the directory shows.
     */
    private function fullNameExpression(QueryBuilder $query): string
    {
        $optional = fn (string $column): string => 'CASE WHEN '
            .$this->searchableText($query, $column)." <> '' THEN "
            .$this->concat($query, "' '", $this->searchableText($query, $column))
            ." ELSE '' END";

        return $this->concat(
            $query,
            $this->searchableText($query, 'users.first_name'),
            $optional('users.middle_name'),
            $this->concat($query, "' '", $this->searchableText($query, 'users.last_name')),
            $optional('users.suffix'),
        );
    }

    private function concat(QueryBuilder $query, string ...$parts): string
    {
        return $query->getConnection()->getDriverName() === 'mysql'
            ? 'CONCAT('.implode(', ', $parts).')'
            : '('.implode(' || ', $parts).')';
    }

    private function searchableText(QueryBuilder $query, string $column): string
    {
        return 'LOWER('.$this->text($query, $column).')';
    }

    private function sortableText(QueryBuilder $query, string $column): string
    {
        return 'LOWER('.$this->text($query, $column).')';
    }

    private function text(QueryBuilder $query, string $column): string
    {
        $cast = $query->getConnection()->getDriverName() === 'mysql'
            ? "CAST({$column} AS CHAR)"
            : "CAST({$column} AS TEXT)";

        return "COALESCE({$cast}, '')";
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function escapeLikeLiteral(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
