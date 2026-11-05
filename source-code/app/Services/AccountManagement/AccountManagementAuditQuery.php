<?php

namespace App\Services\AccountManagement;

use App\Models\AccountManagementAudit;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only, newest-first account-management audit browsing.
 *
 * The service exposes no create, update, or delete path: it only AND-composes
 * the selected action, actor, affected-account, organization, and time-range
 * filters, orders by `occurred_at DESC, id DESC`, and returns credential-free
 * immutable view models. A rejected filter projects no history at all, so an
 * invalid request can never broaden the requested audit trail.
 */
final readonly class AccountManagementAuditQuery
{
    /** @param array<string, mixed> $input Canonical GET filter state. */
    public function browse(
        array $input,
        int $perPage = AccountManagementAuditFilter::DEFAULT_PER_PAGE,
    ): AuditBrowseResult {
        $parse = AuditFilterParse::fromInput($input, $perPage);

        if (! $parse->acceptable()) {
            return new RejectedAuditFilter($parse->filter, $parse->rejections, $parse->submitted);
        }

        return $this->page($parse->filter);
    }

    public function page(AccountManagementAuditFilter $filter): AuditHistoryPage
    {
        $total = $this->filtered($filter)->count();

        $records = $this->filtered($filter)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->forPage($filter->page, $filter->perPage)
            ->get();

        $entries = [];

        foreach ($records as $record) {
            $entries[] = AuditEntryView::fromRecord($record);
        }

        return new AuditHistoryPage($filter, $entries, $total);
    }

    /** @return Builder<AccountManagementAudit> */
    private function filtered(AccountManagementAuditFilter $filter): Builder
    {
        $query = AccountManagementAudit::query()->select([
            'id',
            'action',
            'actor_user_id',
            'actor_display',
            'affected_user_id',
            'affected_display',
            'organization_id',
            'organization_display',
            'changes',
            'occurred_at',
        ]);

        if ($filter->action !== null) {
            $query->where('action', $filter->action->value);
        }

        if ($filter->actor !== null) {
            $query->where('actor_user_id', $filter->actor->value);
        }

        if ($filter->affectedAccount !== null) {
            $query->where('affected_user_id', $filter->affectedAccount->value);
        }

        if ($filter->organization !== null) {
            $query->where('organization_id', $filter->organization->value);
        }

        if ($filter->timeRange?->start !== null) {
            $query->where('occurred_at', '>=', $filter->timeRange->start);
        }

        if ($filter->timeRange?->end !== null) {
            $query->where('occurred_at', '<=', $filter->timeRange->end);
        }

        return $query;
    }
}
