<?php

namespace Tests\Unit\AccountManagement;

use App\Models\AccountManagementAudit;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\AccountManagement\AccountDetailsResult;
use App\Services\AccountManagement\AccountState;
use App\Services\AccountManagement\DeanAppointmentStatus;
use App\Services\AccountManagement\DirectoryContext;
use App\Services\AccountManagement\ManagedAccountDetailsQuery;
use App\Services\AccountManagement\ResolvedAccountDetails;
use App\Services\AccountManagement\SystemAccessRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** **Validates: Requirements 2.3, 2.4, 2.5, 2.6** */
final class ManagedAccountDetailsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_projects_only_the_requested_target_profile_state_assignments_and_dean_status(): void
    {
        $organization = Organization::factory()->create(['name' => 'Computer Society']);
        $other = User::factory()->create(['is_active' => true]);
        $target = User::factory()->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'role' => 'super_admin',
            'is_active' => true,
            'course' => 'BSIT',
        ]);

        OrganizationUser::factory()->create([
            'user_id' => $target->getKey(),
            'organization_id' => $organization->getKey(),
            'role' => 'Senior Adviser',
        ]);
        OrganizationUser::factory()->pending()->create([
            'user_id' => $target->getKey(),
            'organization_id' => $organization->getKey(),
            'role' => 'Treasurer',
        ]);
        OrganizationUser::factory()->create([
            'user_id' => $other->getKey(),
            'organization_id' => $organization->getKey(),
            'role' => 'President',
        ]);

        $result = $this->query()->findDetails((int) $target->getKey(), DirectoryContext::empty());

        self::assertInstanceOf(ResolvedAccountDetails::class, $result);
        self::assertTrue($result->resolved());
        self::assertNull($result->reasonCode());
        self::assertSame((int) $target->getKey(), $result->accountId);
        self::assertSame('Ada Lovelace', $result->fullName);
        self::assertSame(SystemAccessRole::SuperAdmin, $result->systemAccessRole);
        self::assertSame(AccountState::Active, $result->state);
        self::assertSame('Active', $result->stateLabel());
        self::assertSame(DeanAppointmentStatus::CurrentDean, $result->deanStatus);
        self::assertTrue($result->deanStatus->holdsAppointment());
        self::assertArrayNotHasKey('password', $result->profileValues);
        self::assertArrayNotHasKey('role', $result->profileValues);
        self::assertSame('BSIT', $result->profileValues['course']);

        self::assertCount(1, $result->assignments);
        self::assertSame((int) $target->getKey(), $result->assignments[0]->userId);
        self::assertSame('Senior Adviser', $result->assignments[0]->role);
        self::assertSame('Computer Society', $result->assignments[0]->organizationName);
    }

    public function test_it_returns_the_ten_newest_target_scoped_audit_entries(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
        $target = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);

        $expected = [];
        for ($index = 1; $index <= 12; $index++) {
            $audit = AccountManagementAudit::createCompleted(
                AccountManagementAudit::ACTION_PROFILE_UPDATED,
                $actor,
                'OSAS administrator',
                $target,
                'Managed account',
                null,
                null,
                ['profile' => ['course' => ['before' => 'BSIT', 'after' => 'BSCS']], 'sequence' => $index],
                now()->subMinutes(20 - $index),
            );
            $expected[] = (int) $audit->getKey();
        }

        AccountManagementAudit::createCompleted(
            AccountManagementAudit::ACTION_ROLE_CHANGED,
            $actor,
            'OSAS administrator',
            $other,
            'Unrelated account',
            null,
            null,
            ['role' => ['before' => 'student', 'after' => 'admin']],
            now(),
        );

        $result = $this->query()->findDetails((int) $target->getKey(), DirectoryContext::empty());

        self::assertInstanceOf(ResolvedAccountDetails::class, $result);
        self::assertTrue($result->auditAvailable);
        self::assertCount(ResolvedAccountDetails::MAX_AUDIT_ENTRIES, $result->auditEntries);

        $newest = array_reverse(array_slice($expected, 2));
        self::assertSame(
            $newest,
            array_map(static fn ($entry): int => $entry->id, $result->auditEntries),
        );

        foreach ($result->auditEntries as $entry) {
            self::assertSame((int) $target->getKey(), $entry->affectedAccountId);
        }
    }

    public function test_it_returns_a_not_found_result_that_preserves_directory_context(): void
    {
        $context = DirectoryContext::fromParameters(['q' => 'ada', 'role' => 'student', 'state' => 'active', 'page' => '3']);
        $missingId = (int) User::factory()->create()->getKey() + 5000;

        $result = $this->query()->findDetails($missingId, $context);

        self::assertFalse($result->resolved());
        self::assertSame(AccountDetailsResult::NOT_FOUND, $result->reasonCode());
        self::assertSame(
            ['q' => 'ada', 'role' => 'student', 'state' => 'active', 'page' => '3'],
            $result->context()->queryParameters(),
        );
    }

    public function test_it_keeps_details_available_when_audit_entries_cannot_be_retrieved(): void
    {
        $organization = Organization::factory()->create(['name' => 'Computer Society']);
        $target = User::factory()->create(['is_active' => false]);
        OrganizationUser::factory()->create([
            'user_id' => $target->getKey(),
            'organization_id' => $organization->getKey(),
            'role' => 'Auditor',
        ]);

        Schema::drop('account_management_audits');

        $result = $this->query()->findDetails((int) $target->getKey(), DirectoryContext::empty());

        self::assertInstanceOf(ResolvedAccountDetails::class, $result);
        self::assertTrue($result->resolved());
        self::assertFalse($result->auditAvailable);
        self::assertSame(AccountDetailsResult::AUDIT_UNAVAILABLE, $result->reasonCode());
        self::assertSame([], $result->auditEntries);
        self::assertSame('Inactive', $result->stateLabel());
        self::assertCount(1, $result->assignments);
    }

    public function test_it_fails_closed_when_target_isolation_cannot_be_proven(): void
    {
        $context = DirectoryContext::fromParameters(['q' => 'lovelace']);
        $target = User::factory()->create(['is_active' => true]);

        Schema::drop('organization_user');

        $result = $this->query()->findDetails((int) $target->getKey(), $context);

        self::assertFalse($result->resolved());
        self::assertSame(AccountDetailsResult::ISOLATION_FAILURE, $result->reasonCode());
        self::assertSame(['q' => 'lovelace'], $result->context()->queryParameters());
        self::assertNotInstanceOf(ResolvedAccountDetails::class, $result);
    }

    private function query(): ManagedAccountDetailsQuery
    {
        return $this->app->make(ManagedAccountDetailsQuery::class);
    }
}
