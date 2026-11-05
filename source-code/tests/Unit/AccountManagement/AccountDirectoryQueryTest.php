<?php

namespace Tests\Unit\AccountManagement;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\AccountManagement\AccountDirectoryFilter;
use App\Services\AccountManagement\AccountDirectoryQuery;
use App\Services\AccountManagement\AccountState;
use App\Services\AccountManagement\StudentAccountValidity;
use App\Services\AccountManagement\SystemAccessRole;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** **Validates: Requirements 2.1, 2.2** */
final class AccountDirectoryQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_parses_only_canonical_directory_state(): void
    {
        $filter = AccountDirectoryFilter::fromQuery([
            'q' => "  Dela Cruz\t",
            'role' => 'osas_admin',
            'state' => 'inactive',
            'page' => '3',
        ]);

        self::assertSame('Dela Cruz', $filter->search);
        self::assertSame(SystemAccessRole::OsasAdmin, $filter->role);
        self::assertSame(AccountState::Inactive, $filter->state);
        self::assertSame(3, $filter->page);
        self::assertSame(
            ['q' => 'Dela Cruz', 'role' => 'osas_admin', 'state' => 'inactive'],
            $filter->toQueryParameters(),
        );
        self::assertSame(
            ['q' => 'Dela Cruz', 'role' => 'osas_admin', 'state' => 'inactive', 'page' => '3'],
            $filter->toContextParameters(),
        );
    }

    public function test_it_drops_unrecognized_filter_values_instead_of_guessing(): void
    {
        $filter = AccountDirectoryFilter::fromQuery([
            'q' => '   ',
            'role' => 'dean',
            'state' => 'Active',
            'page' => '0',
        ]);

        self::assertNull($filter->search);
        self::assertNull($filter->role);
        self::assertNull($filter->state);
        self::assertSame(1, $filter->page);
        self::assertSame([], $filter->toQueryParameters());
    }

    public function test_it_matches_full_name_email_and_student_id_case_insensitively(): void
    {
        $target = User::factory()->create([
            'first_name' => 'Juan',
            'middle_name' => 'Carlos',
            'last_name' => 'Dela Cruz',
            'suffix' => 'Jr.',
            'email' => 'Juan.Delacruz@example.test',
            'student_id' => '2021-00042',
        ]);
        User::factory()->create([
            'first_name' => 'Maria',
            'middle_name' => null,
            'last_name' => 'Santos',
            'suffix' => null,
            'email' => 'maria.santos@example.test',
            'student_id' => '2021-00099',
        ]);

        self::assertSame([$target->id], $this->resultIds('carlos dela cruz'));
        self::assertSame([$target->id], $this->resultIds('JUAN.DELACRUZ@EXAMPLE.TEST'));
        self::assertSame([$target->id], $this->resultIds('2021-00042'));
    }

    public function test_it_matches_a_full_name_without_a_middle_name(): void
    {
        $target = User::factory()->create([
            'first_name' => 'Maria',
            'middle_name' => null,
            'last_name' => 'Santos',
            'suffix' => null,
        ]);

        self::assertSame([$target->id], $this->resultIds('maria santos'));
    }

    public function test_it_treats_search_wildcards_as_literal_characters(): void
    {
        User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Reyes']);
        $literal = User::factory()->create(['first_name' => 'A%B', 'last_name' => 'Ramos']);

        // A wildcard search would reach "Ana Reyes"; a literal one reaches only "A%B".
        self::assertSame([$literal->id], $this->resultIds('a%'));
        self::assertSame([$literal->id], $this->resultIds('A%B'));
        self::assertSame([], $this->resultIds('An_'));
    }

    public function test_it_and_composes_search_role_and_state_filters(): void
    {
        $match = User::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'role' => 'admin',
            'is_active' => true,
        ]);
        User::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'role' => 'student',
            'is_active' => true,
        ]);
        User::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'role' => 'admin',
            'is_active' => false,
        ]);
        User::factory()->create([
            'first_name' => 'Bea',
            'last_name' => 'Lim',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $results = $this->resultIds('ana reyes', ['role' => 'admin', 'state' => 'active']);

        self::assertSame([$match->id], $results);
    }

    public function test_a_non_matching_search_returns_a_successful_empty_page(): void
    {
        User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Reyes']);

        $page = (new AccountDirectoryQuery)->paginate(
            AccountDirectoryFilter::fromQuery(['q' => 'no such person'])
        );

        self::assertSame(0, $page->total());
        self::assertSame([], $page->items());
    }

    public function test_it_filters_by_the_fixed_validity_window(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');

        try {
            $active = User::factory()->create([
                'first_name' => 'Ana', 'last_name' => 'Active',
                'expires_at' => now()->addMonth(),
            ]);
            $noExpiry = User::factory()->create([
                'first_name' => 'Ana', 'last_name' => 'None',
                'expires_at' => null,
            ]);
            $expiring = User::factory()->create([
                'first_name' => 'Ana', 'last_name' => 'Expiring',
                'expires_at' => now()->subMonth(),
            ]);
            $expired = User::factory()->create([
                'first_name' => 'Ana', 'last_name' => 'Expired',
                'expires_at' => now()->subMonths(StudentAccountValidity::GRACE_MONTHS + 1),
            ]);

            self::assertSame([$active->id, $noExpiry->id], $this->resultIds(null, ['validity' => 'active']));
            self::assertSame([$noExpiry->id], $this->resultIds(null, ['validity' => 'none']));
            self::assertSame([$expiring->id], $this->resultIds(null, ['validity' => 'expiring']));
            self::assertSame([$expired->id], $this->resultIds(null, ['validity' => 'expired']));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_it_loads_only_the_accounts_own_active_assignment_summaries(): void
    {
        $organization = Organization::factory()->create(['name' => 'Robotics Club']);
        $other = Organization::factory()->create(['name' => 'Chess Club']);
        $target = User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Reyes']);
        $unrelated = User::factory()->create(['first_name' => 'Bea', 'last_name' => 'Lim']);

        OrganizationUser::createAssignment($target, $organization, 'President', 'Active');
        OrganizationUser::createAssignment($target, $other, 'Secretary', 'Active');
        OrganizationUser::createAssignment($target, $other, 'Treasurer', 'Pending');
        OrganizationUser::createAssignment($unrelated, $organization, 'Auditor', 'Active');

        $page = (new AccountDirectoryQuery)->paginate(
            AccountDirectoryFilter::fromQuery(['q' => 'ana reyes'])
        );

        self::assertSame([$target->id], collect($page->items())->pluck('id')->all());

        /** @var User $account */
        $account = $page->items()[0];
        $assignments = $account->activeOrganizationAssignments;

        self::assertCount(2, $assignments);
        self::assertSame(['President', 'Secretary'], $assignments->pluck('role')->sort()->values()->all());
        self::assertSame(
            ['Active', 'Active'],
            $assignments->pluck('membership_status')->all(),
        );
        self::assertNotNull($assignments->first()->organization->name);
    }

    public function test_the_directory_projection_excludes_credential_values(): void
    {
        User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Reyes']);

        /** @var User $account */
        $account = (new AccountDirectoryQuery)
            ->paginate(AccountDirectoryFilter::unfiltered())
            ->items()[0];

        self::assertArrayNotHasKey('password', $account->getAttributes());
        self::assertArrayNotHasKey('remember_token', $account->getAttributes());
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<int>
     */
    private function resultIds(?string $search, array $filters = []): array
    {
        $filter = AccountDirectoryFilter::fromQuery(
            array_merge($search === null ? [] : ['q' => $search], $filters)
        );

        return (new AccountDirectoryQuery)
            ->paginate($filter)
            ->getCollection()
            ->map(static fn (User $account): int => (int) $account->id)
            ->values()
            ->all();
    }
}
