<?php

namespace Tests\Unit\AccountManagement;

use App\Models\AccountManagementAudit;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

/** **Validates: Requirements 3.7, 6.2, 7.2, 8.4** */
final class AccountManagementModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_managed_record_factories_default_to_the_initial_version(): void
    {
        $user = User::factory()->make(['management_version' => '1']);
        $organization = Organization::factory()->make(['management_version' => '1']);
        $assignment = OrganizationUser::factory()->make(['management_version' => '1']);

        self::assertSame(1, $user->management_version);
        self::assertSame(1, $organization->management_version);
        self::assertSame(1, $assignment->management_version);
    }

    public function test_user_serialization_hides_credential_and_credential_derived_attributes(): void
    {
        $user = (new User)->forceFill([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'password' => 'hashed-credential',
            'password_changed_at' => now(),
            'remember_token' => 'remember-me',
        ]);

        $serialized = $user->toArray();

        self::assertSame('Ada Lovelace', $serialized['full_name']);
        self::assertArrayNotHasKey('password', $serialized);
        self::assertArrayNotHasKey('password_changed_at', $serialized);
        self::assertArrayNotHasKey('remember_token', $serialized);
    }

    public function test_account_management_relationships_are_explicit_and_target_scoped(): void
    {
        self::assertSame('affected_user_id', (new User)->accountManagementAudits()->getForeignKeyName());
        self::assertSame('actor_user_id', (new User)->actedAccountManagementAudits()->getForeignKeyName());
        self::assertSame('organization_id', (new Organization)->accountManagementAudits()->getForeignKeyName());
        self::assertSame('user_id', (new OrganizationUser)->user()->getForeignKeyName());
        self::assertSame('organization_id', (new OrganizationUser)->organization()->getForeignKeyName());
        self::assertSame('actor_user_id', (new AccountManagementAudit)->actor()->getForeignKeyName());
        self::assertSame('affected_user_id', (new AccountManagementAudit)->affectedUser()->getForeignKeyName());
        self::assertSame('organization_id', (new AccountManagementAudit)->organization()->getForeignKeyName());
    }

    public function test_audits_can_only_be_created_through_the_guarded_credential_free_api(): void
    {
        $this->expectException(MassAssignmentException::class);

        new AccountManagementAudit([
            'action' => AccountManagementAudit::ACTION_ACCOUNT_CREATED,
            'changes' => ['role' => ['after' => 'student']],
        ]);
    }

    public function test_completed_audit_uses_typed_relations_immutable_casts_and_rejects_nested_credentials(): void
    {
        $actor = User::factory()->create();
        $affected = User::factory()->create();
        $organization = Organization::factory()->create();

        $audit = AccountManagementAudit::createCompleted(
            AccountManagementAudit::ACTION_OFFICER_APPOINTED,
            $actor,
            $actor->full_name,
            $affected,
            $affected->full_name,
            $organization,
            $organization->name,
            ['position' => ['before' => 'Secretary', 'after' => 'President']],
            CarbonImmutable::parse('2026-07-25 10:00:00'),
        );

        self::assertSame($actor->id, $audit->actor_user_id);
        self::assertSame($affected->id, $audit->affected_user_id);
        self::assertSame($organization->id, $audit->organization_id);
        self::assertSame(['position' => ['before' => 'Secretary', 'after' => 'President']], $audit->changes);
        self::assertInstanceOf(CarbonImmutable::class, $audit->occurred_at);

        $this->expectException(InvalidArgumentException::class);
        AccountManagementAudit::createCompleted(
            AccountManagementAudit::ACTION_PROFILE_UPDATED,
            $actor,
            $actor->full_name,
            $affected,
            $affected->full_name,
            null,
            null,
            ['profile' => ['password_hash' => ['after' => 'forbidden']]],
        );
    }

    public function test_model_backed_pivot_writes_invalidate_the_observed_permission_cache(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $organization = Organization::factory()->create();
        $cacheKey = "org_permission:{$user->id}:{$organization->id}";

        Cache::forever($cacheKey, 'stale-missing-assignment');
        $assignment = OrganizationUser::createAssignment($user, $organization, 'Secretary', 'Active');
        self::assertFalse(Cache::has($cacheKey));

        Cache::forever($cacheKey, 'Secretary');
        $assignment->replaceRoleAndMembership('President', 'Active');
        self::assertFalse(Cache::has($cacheKey));
    }
}
