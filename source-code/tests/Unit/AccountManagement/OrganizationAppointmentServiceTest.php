<?php

namespace Tests\Unit\AccountManagement;

use App\Models\AccountManagementAudit;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Policies\OsasAccountPolicy;
use App\Services\AccountManagement\AccountManagementAuditWriter;
use App\Services\AccountManagement\AccountManagementFailure;
use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\AdviserPosition;
use App\Services\AccountManagement\CompletedAccountManagementCommand;
use App\Services\AccountManagement\FreshnessSnapshot;
use App\Services\AccountManagement\FreshnessVerifier;
use App\Services\AccountManagement\ManagedAccountId;
use App\Services\AccountManagement\OfficerPosition;
use App\Services\AccountManagement\OrganizationAppointmentCommand;
use App\Services\AccountManagement\OrganizationAppointmentService;
use App\Services\AccountManagement\OrganizationId;
use App\Services\AccountManagement\RejectedAccountManagementCommand;
use App\Services\OrganizationPermissionService;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** **Validates: Requirements 6.2, 6.3, 6.4, 6.5, 8.2, 8.4, 8.5, 8.6** */
final class OrganizationAppointmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_eligible_student_is_promoted_and_appointed_with_one_audit_entry(): void
    {
        $actor = $this->actor();
        $organization = $this->eligibleOrganization();
        $candidate = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $result = $this->service()->appointAdviser($this->command(
            $actor,
            $organization,
            $candidate,
            AdviserPosition::SeniorAdviser,
        ));

        self::assertInstanceOf(CompletedAccountManagementCommand::class, $result);
        self::assertSame('admin', $candidate->fresh()->role);
        self::assertTrue($candidate->fresh()->is_active);
        self::assertSame($candidate->id, $this->occupantOf($organization, 'Senior Adviser'));

        $audit = AccountManagementAudit::query()->sole();
        self::assertSame(AccountManagementAudit::ACTION_ADVISER_APPOINTED, $audit->action);
        self::assertSame($organization->id, $audit->organization_id);
        self::assertSame($candidate->id, $audit->affected_user_id);
        self::assertSame('Senior Adviser', $audit->changes['position']);
        self::assertSame($candidate->id, $audit->changes['selected_assignee']['id']);
        self::assertArrayNotHasKey('former_assignee', $audit->changes);
        self::assertSame(['before' => 'student', 'after' => 'admin'], $audit->changes['role']);
    }

    public function test_a_replacement_changes_only_the_requested_slot_and_records_the_former_assignee(): void
    {
        $actor = $this->actor();
        $organization = $this->eligibleOrganization();
        $former = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $candidate = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->assign($organization, $former, 'Junior Adviser');
        $unrelated = $this->assign($organization, $former, 'Treasurer');
        $otherAdviser = $this->assign($organization, User::factory()->create(['role' => 'admin']), 'Senior Adviser');
        $this->primePermissionCache($organization, $former, $candidate);

        $result = $this->service()->appointAdviser($this->command(
            $actor,
            $organization,
            $candidate,
            AdviserPosition::JuniorAdviser,
        ));

        self::assertTrue($result->completed());
        self::assertSame($candidate->id, $this->occupantOf($organization, 'Junior Adviser'));
        self::assertSame('admin', $former->fresh()->role);
        self::assertTrue($former->fresh()->is_active);
        self::assertNotNull(OrganizationUser::query()->find($unrelated->id));
        self::assertSame((int) $otherAdviser->user_id, $this->occupantOf($organization, 'Senior Adviser'));

        $audit = AccountManagementAudit::query()->sole();
        self::assertSame('Junior Adviser', $audit->changes['position']);
        self::assertSame($former->id, $audit->changes['former_assignee']['id']);
        self::assertSame($candidate->id, $audit->changes['selected_assignee']['id']);
        self::assertSame(['before' => 'admin', 'after' => 'admin'], $audit->changes['role']);

        self::assertFalse(Cache::has($this->permissionCacheKey($organization, $former)));
        self::assertFalse(Cache::has($this->permissionCacheKey($organization, $candidate)));
    }

    public function test_no_account_can_hold_both_adviser_slots(): void
    {
        $actor = $this->actor();
        $organization = $this->eligibleOrganization();
        $candidate = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->assign($organization, $candidate, 'Senior Adviser');

        $result = $this->service()->appointAdviser($this->command(
            $actor,
            $organization,
            $candidate,
            AdviserPosition::JuniorAdviser,
        ));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementFailure::Conflict, $result->failure);
        self::assertArrayHasKey('candidate', $result->fieldErrors);
        self::assertNull($this->occupantOf($organization, 'Junior Adviser'));
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_invalid_requests_preserve_every_appointment_role_and_audit_record(): void
    {
        $actor = $this->actor();
        $organization = $this->eligibleOrganization();
        $archived = Organization::factory()->create(['status' => 'Approved', 'archived_at' => now()->subDay()]);
        $seated = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->assign($organization, $seated, 'Senior Adviser');

        $inactive = User::factory()->create(['role' => 'student', 'is_active' => false]);
        $privileged = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
        $candidate = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $missingId = (int) User::query()->max('id') + 500;

        $service = $this->service();
        $staleCommand = $this->command($actor, $organization, $candidate, AdviserPosition::JuniorAdviser);
        $organization->forceFill(['name' => $organization->name.' Updated'])->save();

        $pivots = $this->pivotSnapshot();
        $users = $this->userSnapshot();

        $rejections = [
            'inactive candidate' => $service->appointAdviser(
                $this->command($actor, $organization, $inactive, AdviserPosition::JuniorAdviser),
            ),
            'privileged candidate' => $service->appointAdviser(
                $this->command($actor, $organization, $privileged, AdviserPosition::JuniorAdviser),
            ),
            'missing candidate' => $service->appointAdviser(OrganizationAppointmentCommand::forAdviser(
                new ActorAccountId((int) $actor->id),
                new OrganizationId((int) $organization->id),
                new ManagedAccountId($missingId),
                AdviserPosition::JuniorAdviser,
                $this->missingCandidateSnapshot($actor, $organization, $missingId),
            )),
            'ineligible organization' => $service->appointAdviser(
                $this->command($actor, $archived, $candidate, AdviserPosition::JuniorAdviser),
            ),
            'already seated' => $service->appointAdviser(
                $this->command($actor, $organization, $seated, AdviserPosition::SeniorAdviser),
            ),
            'stale snapshot' => $service->appointAdviser($staleCommand),
        ];

        foreach ($rejections as $label => $rejection) {
            self::assertInstanceOf(RejectedAccountManagementCommand::class, $rejection, $label);
            self::assertFalse($rejection->completed(), $label);
        }

        self::assertSame(AccountManagementFailure::Ineligible, $rejections['inactive candidate']->failure);
        self::assertSame(AccountManagementFailure::Ineligible, $rejections['privileged candidate']->failure);
        self::assertSame(AccountManagementFailure::NotFound, $rejections['missing candidate']->failure);
        self::assertSame(AccountManagementFailure::Ineligible, $rejections['ineligible organization']->failure);
        self::assertSame(AccountManagementFailure::Invalid, $rejections['already seated']->failure);
        self::assertSame(AccountManagementFailure::Stale, $rejections['stale snapshot']->failure);
        self::assertNotNull($rejections['stale snapshot']->reload);

        self::assertSame($pivots, $this->pivotSnapshot());
        self::assertSame($users, $this->userSnapshot());
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_a_non_osas_actor_cannot_appoint_an_adviser(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $organization = $this->eligibleOrganization();
        $candidate = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $result = $this->service()->appointAdviser($this->command(
            $actor,
            $organization,
            $candidate,
            AdviserPosition::SeniorAdviser,
        ));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementFailure::Unauthorized, $result->failure);
        self::assertSame('student', $candidate->fresh()->role);
        self::assertNull($this->occupantOf($organization, 'Senior Adviser'));
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_officer_appointment_replaces_slot_and_preserves_unrelated_assignments(): void
    {
        $actor = $this->actor();
        $organization = $this->eligibleOrganization();
        $former = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $candidate = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->assign($organization, $former, 'President');
        $unrelated = $this->assign($organization, $former, 'Senior Adviser');
        $otherOfficer = $this->assign($organization, User::factory()->create(['role' => 'admin']), 'Vice President');
        $this->primePermissionCache($organization, $former, $candidate);

        $result = $this->service()->appointOfficer($this->command(
            $actor,
            $organization,
            $candidate,
            OfficerPosition::President,
        ));

        self::assertTrue($result->completed());
        self::assertSame($candidate->id, $this->occupantOf($organization, 'President'));
        self::assertSame('admin', $former->fresh()->role);
        self::assertNotNull(OrganizationUser::query()->find($unrelated->id));
        self::assertSame((int) $otherOfficer->user_id, $this->occupantOf($organization, 'Vice President'));

        $audit = AccountManagementAudit::query()->sole();
        self::assertSame(AccountManagementAudit::ACTION_OFFICER_APPOINTED, $audit->action);
        self::assertSame('President', $audit->changes['position']);
        self::assertSame($former->id, $audit->changes['former_assignee']['id']);
        self::assertSame($candidate->id, $audit->changes['selected_assignee']['id']);
        self::assertSame(['before' => 'student', 'after' => 'admin'], $audit->changes['role']);

        self::assertFalse(Cache::has($this->permissionCacheKey($organization, $former)));
        self::assertFalse(Cache::has($this->permissionCacheKey($organization, $candidate)));
    }

    public function test_account_cannot_hold_multiple_officer_positions_in_same_organization(): void
    {
        $actor = $this->actor();
        $organization = $this->eligibleOrganization();
        $candidate = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->assign($organization, $candidate, 'President');

        $result = $this->service()->appointOfficer($this->command(
            $actor,
            $organization,
            $candidate,
            OfficerPosition::VicePresident,
        ));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementFailure::Conflict, $result->failure);
        self::assertArrayHasKey('candidate', $result->fieldErrors);
        self::assertNull($this->occupantOf($organization, 'Vice President'));
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_invalid_officer_requests_are_noninterfering(): void
    {
        $actor = $this->actor();
        $organization = $this->eligibleOrganization();
        $candidate = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $seated = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->assign($organization, $seated, 'President');
        $inactive = User::factory()->create(['role' => 'student', 'is_active' => false]);

        $service = $this->service();

        $pivots = $this->pivotSnapshot();
        $users = $this->userSnapshot();

        $rejections = [
            'inactive candidate' => $service->appointOfficer(
                $this->command($actor, $organization, $inactive, OfficerPosition::President)
            ),
            'already seated' => $service->appointOfficer(
                $this->command($actor, $organization, $seated, OfficerPosition::President)
            ),
        ];

        foreach ($rejections as $label => $rejection) {
            self::assertInstanceOf(RejectedAccountManagementCommand::class, $rejection, $label);
            self::assertFalse($rejection->completed(), $label);
        }

        self::assertSame(AccountManagementFailure::Ineligible, $rejections['inactive candidate']->failure);
        self::assertSame(AccountManagementFailure::Invalid, $rejections['already seated']->failure);

        self::assertSame($pivots, $this->pivotSnapshot());
        self::assertSame($users, $this->userSnapshot());
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    private function service(): OrganizationAppointmentService
    {
        return new OrganizationAppointmentService(
            $this->app->make(OsasAccountPolicy::class),
            new FreshnessVerifier($this->app->make(Encrypter::class)),
            new AccountManagementAuditWriter,
            $this->app->make(OrganizationPermissionService::class),
        );
    }

    private function command(
        User $actor,
        Organization $organization,
        User $candidate,
        AdviserPosition|OfficerPosition $position,
    ): OrganizationAppointmentCommand {
        $snapshot = $this->service()->currentFreshness(
            new ActorAccountId((int) $actor->id),
            new OrganizationId((int) $organization->id),
            new ManagedAccountId((int) $candidate->id),
            $position,
        );

        self::assertNotNull($snapshot);

        if ($position instanceof AdviserPosition) {
            return OrganizationAppointmentCommand::forAdviser(
                new ActorAccountId((int) $actor->id),
                new OrganizationId((int) $organization->id),
                new ManagedAccountId((int) $candidate->id),
                $position,
                $snapshot,
            );
        }

        return OrganizationAppointmentCommand::forOfficer(
            new ActorAccountId((int) $actor->id),
            new OrganizationId((int) $organization->id),
            new ManagedAccountId((int) $candidate->id),
            $position,
            $snapshot,
        );
    }

    private function missingCandidateSnapshot(User $actor, Organization $organization, int $candidateId): FreshnessSnapshot
    {
        $existing = $this->service()->currentFreshness(
            new ActorAccountId((int) $actor->id),
            new OrganizationId((int) $organization->id),
            new ManagedAccountId((int) $actor->id),
            AdviserPosition::JuniorAdviser,
        );

        self::assertNotNull($existing);

        return new FreshnessSnapshot(
            operation: $existing->operation,
            actorId: $existing->actorId,
            targetId: $candidateId,
            users: $existing->users,
            organization: $existing->organization,
            slot: $existing->slot,
            conflictingPivots: $existing->conflictingPivots,
        );
    }

    private function actor(): User
    {
        return User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
    }

    private function eligibleOrganization(): Organization
    {
        return Organization::factory()->create([
            'status' => 'Approved',
            'archived_at' => null,
            'accreditation_expires_at' => now()->addYear(),
        ]);
    }

    private function assign(Organization $organization, User $user, string $role): OrganizationUser
    {
        return OrganizationUser::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'membership_status' => 'Active',
        ]);
    }

    private function occupantOf(Organization $organization, string $role): ?int
    {
        $occupant = DB::table('organization_user')
            ->where('organization_id', $organization->id)
            ->where('membership_status', 'Active')
            ->where('role', $role)
            ->value('user_id');

        return $occupant === null ? null : (int) $occupant;
    }

    private function primePermissionCache(Organization $organization, User ...$users): void
    {
        foreach ($users as $user) {
            Cache::put($this->permissionCacheKey($organization, $user), ['stale' => true], 600);
            self::assertTrue(Cache::has($this->permissionCacheKey($organization, $user)));
        }
    }

    private function permissionCacheKey(Organization $organization, User $user): string
    {
        return "org_permission:{$user->id}:{$organization->id}";
    }

    /** @return list<array<string, mixed>> */
    private function pivotSnapshot(): array
    {
        return DB::table('organization_user')
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function userSnapshot(): array
    {
        return DB::table('users')
            ->orderBy('id')
            ->get(['id', 'role', 'is_active', 'management_version'])
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }
}
