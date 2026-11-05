<?php

namespace Tests\Unit\AccountManagement;

use App\Models\AccountManagementAudit;
use App\Models\Organization;
use App\Models\User;
use App\Services\AccountManagement\AccountManagementAction;
use App\Services\AccountManagement\AccountManagementAuditFilter;
use App\Services\AccountManagement\AccountManagementAuditQuery;
use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\AuditEntryView;
use App\Services\AccountManagement\AuditFilterRejection;
use App\Services\AccountManagement\AuditHistoryPage;
use App\Services\AccountManagement\RejectedAuditFilter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/** **Validates: Requirements 9.1, 9.2, 9.3, 9.4, 9.6** */
final class AccountManagementAuditQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_entries_are_newest_first_and_tie_break_on_descending_id(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin']);
        $target = User::factory()->create();
        $tied = CarbonImmutable::parse('2026-03-01 08:00:00');

        $older = $this->audit($actor, $target, AccountManagementAudit::ACTION_PROFILE_UPDATED, $tied->subDay());
        $firstTied = $this->audit($actor, $target, AccountManagementAudit::ACTION_ACCOUNT_DEACTIVATED, $tied);
        $secondTied = $this->audit($actor, $target, AccountManagementAudit::ACTION_ACCOUNT_REACTIVATED, $tied);
        $newest = $this->audit($actor, $target, AccountManagementAudit::ACTION_ROLE_CHANGED, $tied->addDay());

        $page = $this->query()->browse([]);

        self::assertInstanceOf(AuditHistoryPage::class, $page);
        self::assertSame(
            [$newest->id, $secondTied->id, $firstTied->id, $older->id],
            array_map(static fn (AuditEntryView $entry): int => $entry->id, $page->entries),
        );
        self::assertSame(4, $page->total);
    }

    public function test_selected_filters_are_and_composed(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin']);
        $otherActor = User::factory()->create(['role' => 'osas_admin']);
        $target = User::factory()->create();
        $otherTarget = User::factory()->create();
        $organization = Organization::factory()->create();
        $moment = CarbonImmutable::parse('2026-04-10 09:30:00');

        $expected = $this->appointment($actor, $target, $organization, $moment);
        $this->appointment($otherActor, $target, $organization, $moment);
        $this->appointment($actor, $otherTarget, $organization, $moment);
        $this->appointment($actor, $target, Organization::factory()->create(), $moment);
        $this->appointment($actor, $target, $organization, $moment->addMonth());
        $this->audit($actor, $target, AccountManagementAudit::ACTION_ROLE_CHANGED, $moment);

        $page = $this->query()->browse([
            AccountManagementAuditFilter::PARAM_ACTION => 'officer.appointed',
            AccountManagementAuditFilter::PARAM_ACTOR => (string) $actor->id,
            AccountManagementAuditFilter::PARAM_ACCOUNT => (string) $target->id,
            AccountManagementAuditFilter::PARAM_ORGANIZATION => (string) $organization->id,
            AccountManagementAuditFilter::PARAM_FROM => '2026-04-10T09:00',
            AccountManagementAuditFilter::PARAM_TO => '2026-04-10T10:00',
        ]);

        self::assertInstanceOf(AuditHistoryPage::class, $page);
        self::assertSame([$expected->id], array_map(static fn (AuditEntryView $entry): int => $entry->id, $page->entries));
        self::assertSame(1, $page->total);
        self::assertSame(AccountManagementAction::OfficerAppointed, $page->entries[0]->action);
    }

    public function test_a_filter_matching_nothing_is_a_successful_empty_page(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin']);
        $target = User::factory()->create();
        $this->audit($actor, $target, AccountManagementAudit::ACTION_ROLE_CHANGED, CarbonImmutable::parse('2026-01-01 00:00:00'));

        $page = $this->query()->browse([AccountManagementAuditFilter::PARAM_ACTION => 'account.deleted']);

        self::assertInstanceOf(AuditHistoryPage::class, $page);
        self::assertTrue($page->accepted());
        self::assertTrue($page->isEmpty());
        self::assertSame(0, $page->total);
    }

    public function test_a_reversed_time_range_is_rejected_without_normalizing_and_retains_valid_filters(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin']);
        $target = User::factory()->create();
        $this->audit($actor, $target, AccountManagementAudit::ACTION_ROLE_CHANGED, CarbonImmutable::parse('2026-02-02 12:00:00'));

        $result = $this->query()->browse([
            AccountManagementAuditFilter::PARAM_ACTION => 'role.changed',
            AccountManagementAuditFilter::PARAM_ACTOR => (string) $actor->id,
            AccountManagementAuditFilter::PARAM_FROM => '2026-03-01T00:00',
            AccountManagementAuditFilter::PARAM_TO => '2026-02-01T00:00',
        ]);

        self::assertInstanceOf(RejectedAuditFilter::class, $result);
        self::assertFalse($result->accepted());
        self::assertSame([AuditFilterRejection::ReversedTimeRange], $result->rejections);
        self::assertTrue($result->rejectedTimeRange());
        self::assertSame(AccountManagementAction::RoleChanged, $result->filter()->action);
        self::assertSame($actor->id, $result->filter()->actor?->value);
        self::assertNull($result->filter()->timeRange);
        self::assertSame('2026-03-01T00:00', $result->submitted[AccountManagementAuditFilter::PARAM_FROM]);
        self::assertSame('2026-02-01T00:00', $result->submitted[AccountManagementAuditFilter::PARAM_TO]);
    }

    public function test_an_unparsable_time_value_is_rejected_and_never_broadens_the_request(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin']);
        $target = User::factory()->create();
        $this->audit($actor, $target, AccountManagementAudit::ACTION_ROLE_CHANGED, CarbonImmutable::parse('2026-02-02 12:00:00'));

        $result = $this->query()->browse([
            AccountManagementAuditFilter::PARAM_FROM => 'yesterday',
            AccountManagementAuditFilter::PARAM_TO => '2026-13-45T99:99',
        ]);

        self::assertInstanceOf(RejectedAuditFilter::class, $result);
        self::assertSame(
            [AuditFilterRejection::InvalidStartTime, AuditFilterRejection::InvalidEndTime],
            $result->rejections,
        );
        self::assertNull($result->filter()->timeRange);
        self::assertArrayHasKey(AccountManagementAuditFilter::PARAM_FROM, $result->messages());
        self::assertArrayHasKey(AccountManagementAuditFilter::PARAM_TO, $result->messages());
    }

    public function test_an_equal_start_and_end_boundary_is_accepted_and_inclusive(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin']);
        $target = User::factory()->create();
        $boundary = CarbonImmutable::parse('2026-05-05 07:00:00');
        $inside = $this->audit($actor, $target, AccountManagementAudit::ACTION_ROLE_CHANGED, $boundary);
        $this->audit($actor, $target, AccountManagementAudit::ACTION_PROFILE_UPDATED, $boundary->addMinute());

        $page = $this->query()->browse([
            AccountManagementAuditFilter::PARAM_FROM => '2026-05-05T07:00',
            AccountManagementAuditFilter::PARAM_TO => '2026-05-05T07:00',
        ]);

        self::assertInstanceOf(AuditHistoryPage::class, $page);
        self::assertSame([$inside->id], array_map(static fn (AuditEntryView $entry): int => $entry->id, $page->entries));
    }

    public function test_projected_entries_are_credential_free_view_models(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin']);
        $target = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

        DB::table('account_management_audits')->insert([
            'action' => AccountManagementAudit::ACTION_PROFILE_UPDATED,
            'actor_user_id' => $actor->id,
            'actor_display' => 'OSAS Administrator',
            'affected_user_id' => $target->id,
            'affected_display' => 'Ada Lovelace',
            'organization_id' => null,
            'organization_display' => null,
            'changes' => json_encode([
                'profile' => ['first_name' => ['before' => 'Ada', 'after' => 'Grace']],
                'password' => 'plain-text-value',
                'password_hash' => '$2y$04$abcdefghijklmnopqrstuv',
                'nested' => ['remember_token' => 'x', 'keep' => 'visible'],
            ]),
            'occurred_at' => '2026-06-06 06:06:06',
        ]);

        $page = $this->query()->browse([]);

        self::assertInstanceOf(AuditHistoryPage::class, $page);
        $entry = $page->entries[0];

        self::assertInstanceOf(AuditEntryView::class, $entry);
        self::assertSame('Profile updated', $entry->actionLabel);
        self::assertSame('OSAS Administrator', $entry->actorDisplay);
        self::assertSame('Ada Lovelace', $entry->affectedDisplay);
        self::assertSame([
            'profile' => ['first_name' => ['before' => 'Ada', 'after' => 'Grace']],
            'nested' => ['keep' => 'visible'],
        ], $entry->changes);
        self::assertStringNotContainsString('password', strtolower(json_encode($entry->changes)));
    }

    public function test_pagination_keeps_newest_first_order_and_retains_filters_in_links(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin']);
        $target = User::factory()->create();
        $base = CarbonImmutable::parse('2026-07-01 00:00:00');
        $ids = [];

        for ($minute = 0; $minute < 5; $minute++) {
            $ids[] = $this->audit(
                $actor,
                $target,
                AccountManagementAudit::ACTION_ROLE_CHANGED,
                $base->addMinutes($minute),
            )->id;
        }

        $newestFirst = array_reverse($ids);

        $first = $this->query()->page(new AccountManagementAuditFilter(
            actor: new ActorAccountId($actor->id),
            page: 1,
            perPage: 2,
        ));
        $last = $this->query()->page(new AccountManagementAuditFilter(
            actor: new ActorAccountId($actor->id),
            page: 3,
            perPage: 2,
        ));

        self::assertSame(
            array_slice($newestFirst, 0, 2),
            array_map(static fn (AuditEntryView $entry): int => $entry->id, $first->entries),
        );
        self::assertSame(
            array_slice($newestFirst, 4, 1),
            array_map(static fn (AuditEntryView $entry): int => $entry->id, $last->entries),
        );
        self::assertSame(3, $first->lastPage());
        self::assertTrue($first->hasNextPage());
        self::assertFalse($last->hasNextPage());
        self::assertSame(
            [AccountManagementAuditFilter::PARAM_ACTOR => (string) $actor->id, AccountManagementAuditFilter::PARAM_PAGE => '2'],
            $first->queryParametersForPage(2),
        );
    }

    public function test_the_audit_query_exposes_no_mutation_capability(): void
    {
        $methods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(AccountManagementAuditQuery::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        self::assertSame(['browse', 'page'], $methods);
    }

    private function query(): AccountManagementAuditQuery
    {
        return new AccountManagementAuditQuery;
    }

    private function audit(User $actor, User $target, string $action, CarbonImmutable $occurredAt): AccountManagementAudit
    {
        return AccountManagementAudit::createCompleted(
            $action,
            $actor,
            'OSAS Administrator',
            $target,
            'Managed Account',
            null,
            null,
            ['state' => ['before' => 'Active', 'after' => 'Inactive']],
            $occurredAt,
        );
    }

    private function appointment(
        User $actor,
        User $target,
        Organization $organization,
        CarbonImmutable $occurredAt,
    ): AccountManagementAudit {
        return AccountManagementAudit::createCompleted(
            AccountManagementAudit::ACTION_OFFICER_APPOINTED,
            $actor,
            'OSAS Administrator',
            $target,
            'Managed Account',
            $organization,
            $organization->name,
            ['position' => 'President'],
            $occurredAt,
        );
    }
}
