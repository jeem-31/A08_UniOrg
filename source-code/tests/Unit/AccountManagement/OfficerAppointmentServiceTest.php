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
use App\Services\AccountManagement\OrganizationPositionRegistry;
use App\Services\AccountManagement\RejectedAccountManagementCommand;
use App\Services\OrganizationPermissionService;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** **Validates: Requirements 7.2, 7.3, 7.4, 7.5, 7.6, 8.3, 8.4, 8.5, 8.6** */
final class OfficerAppointmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_canonical_officer_position_can_be_filled_exactly_once(): void
    {
        $actor = $this->actor();
        $organization = $this->eligibleOrganization();
        $positions = OrganizationPositionRegistry::officers();
        $service = $this->service();

        foreach ($positions as $position) {
            $candidate = User::factory()->create(['role' => 'student', 'is_active' => true]);

            $result = $service->appointOfficer($this->command($actor, $organization, $candidate, $position));

            self::assertInstanceOf(CompletedAccountManagementCommand::class, $result, $position->value);
            self::assertSame('admin', $candidate->fresh()->role, $position->value);
            self::assertSame(
                $candidate->id,
                $this->occupantOf($organization, $position->role()),
                $position->value,
            );
            self::assertSame(1, $this->slotCount($organization, $position->role()), $position->value);
        }

        self::assertSame(count($positions), AccountManagementAudit::query()->count());
        self::assertSame(
            count($positions),
            AccountManagementAudit::query()
                ->where('action', AccountManagementAudit::ACTION_OFFICER_APPOINTED)
                ->count(),
        );
    }

    public function test_an_eligible_student_is_promoted_and_audited_after_every_check(): void
    {
        $actor = $this->actor();
        $organization = $this->eligibleOrganization();
        $candidate = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $result = $this->service()->appointOfficer($this->command(
            $actor,
            $organization,
            $candidate,
            OfficerPosition::Treasurer,
        ));

        self::assertTrue($result->completed());
        self::assertSame('admin', $candidate->fresh()->role);
        self::assertTrue($candidate->fresh()->is_active);

        $audit = AccountManagementAudit::query()->sole();
        self::assertSame(AccountManagementAudit::ACTION_OFFICER_APPOINTED, $audit->action);
        self::assertSame($organization->id, $audit->organization_id);
        self::assertSame($candidate->id, $audit->affected_user_id);
        self::assertSame('Treasurer', $audit->changes['position']);
        self::assertSame($candidate->id, $audit->changes['selected_assignee']['id']);
        self::assertArrayNotHasKey('former_assignee', $audit->changes);
        self::assertSame(['before' => 'student', 'after' => 'admin'], $audit->changes['role']);
    }

    public function test_a_replacement_changes_only_the_requested_officer_slot(): void
    {
        $actor = $this->actor();
        $organization = $this->eligibleOrganization();
        $otherOrganization = $this->eligibleOrganization();
        $former = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $candidate = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $replaced = $this->assign($organization, $former, 'Secretary');
        $formerElsewhere = $this->assign($otherOrganization, $former, 'President');
        $formerMembership = $this->assign($organization, $former, 'Member');
        $candidateAdviser = $this->assign($organization, $candidate, 'Junior Adviser');
        $untouchedOfficer = $this->assign($organization, User::factory()->create(['role' => 'admin']), 'Auditor');
        $this->primePermissionCache($organization, $former, $candidate);

        $result = $this->service()->appointOfficer($this->command(
            $actor,
            $organization,
            $candidate,
            OfficerPosition::Secretary,
        ));

        self::assertTrue($result->completed());
        self::assertSame($candidate->id, $this->occupantOf($organization, 'Secretary'));
        self::assertSame(1, $this->slotCount($organization, 'Secretary'));
        self::assertNull(OrganizationUser::query()->find($replaced->id));

        // The replaced account and every unrelated assignment survive untouched.
        self::assertSame('admin', $former->fresh()->role);
        self::assertTrue($former->fresh()->is_active);
        self::assertNotNull(OrganizationUser::query()->find($formerElsewhere->id));
        self::assertNotNull(OrganizationUser::query()->find($formerMembership->id));
        self::assertNotNull(OrganizationUser::query()->find($candidateAdviser->id));
        self::assertSame((int) $untouchedOfficer->user_id, $this->occupantOf($organization, 'Auditor'));

        $audit = AccountManagementAudit::query()->sole();
        self::assertSame('Secretary', $audit->changes['position']);
        self::assertSame($former->id, $audit->changes['former_assignee']['id']);
        self::assertSame($candidate->id, $audit->changes['selected_assignee']['id']);
        self::assertSame(['before' => 'admin', 'after' => 'admin'], $audit->changes['role']);

        self::assertFalse(Cache::has($this->permissionCacheKey($organization, $former)));
        self::assertFalse(Cache::has($this->permissionCacheKey($organization, $candidate)));
    }

    public function test_an_account_cannot_hold_two_officer_positions_in_one_organization(): void
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
        self::assertSame($candidate->id, $this->occupantOf($organization, 'President'));
        self::assertNull($this->occupantOf($organization, 'Vice President'));
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_invalid_officer_requests_are_noninterfering(): void
    {
        $actor = $this->actor();
        $organization = $this->eligibleOrganization();
        $archived = Organization::factory()->create(['status' => 'Approved', 'archived_at' => now()->subDay()]);
        $seated = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->assign($organization, $seated, 'Auditor');
        $this->assign($organization, $seated, 'Junior Adviser');

        $inactive = User::factory()->create(['role' => 'student', 'is_active' => false]);
        $privileged = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $candidate = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $missingId = (int) User::query()->max('id') + 500;

        $service = $this->service();
        $staleCommand = $this->command($actor, $organization, $candidate, OfficerPosition::Comelec);
        $organization->forceFill(['name' => $organization->name.' Updated'])->save();

        $pivots = $this->pivotSnapshot();
        $users = $this->userSnapshot();

        $rejections = [
            'inactive candidate' => $service->appointOfficer(
                $this->command($actor, $organization, $inactive, OfficerPosition::Comelec),
            ),
            'privileged candidate' => $service->appointOfficer(
                $this->command($actor, $organization, $privileged, OfficerPosition::Comelec),
            ),
            'missing candidate' => $service->appointOfficer(OrganizationAppointmentCommand::forOfficer(
                new ActorAccountId((int) $actor->id),
                new OrganizationId((int) $organization->id),
                new ManagedAccountId($missingId),
                OfficerPosition::Comelec,
                $this->missingCandidateSnapshot($actor, $organization, $missingId),
            )),
            'ineligible organization' => $service->appointOfficer(
                $this->command($actor, $archived, $candidate, OfficerPosition::Comelec),
            ),
            'already an officer elsewhere' => $service->appointOfficer(
                $this->command($actor, $organization, $seated, OfficerPosition::Comelec),
            ),
            'already seated' => $service->appointOfficer(
                $this->command($actor, $organization, $seated, OfficerPosition::Auditor),
            ),
            'stale snapshot' => $service->appointOfficer($staleCommand),
            'adviser position' => $service->appointOfficer(OrganizationAppointmentCommand::forAdviser(
                new ActorAccountId((int) $actor->id),
                new OrganizationId((int) $organization->id),
                new ManagedAccountId((int) $candidate->id),
                AdviserPosition::SeniorAdviser,
                $this->adviserSnapshot($actor, $organization, $candidate),
            )),
        ];

        foreach ($rejections as $label => $rejection) {
            self::assertInstanceOf(RejectedAccountManagementCommand::class, $rejection, $label);
            self::assertFalse($rejection->completed(), $label);
        }

        self::assertSame(AccountManagementFailure::Ineligible, $rejections['inactive candidate']->failure);
        self::assertSame(AccountManagementFailure::Ineligible, $rejections['privileged candidate']->failure);
        self::assertSame(AccountManagementFailure::NotFound, $rejections['missing candidate']->failure);
        self::assertSame(AccountManagementFailure::Ineligible, $rejections['ineligible organization']->failure);
        self::assertSame(AccountManagementFailure::Conflict, $rejections['already an officer elsewhere']->failure);
        self::assertSame(AccountManagementFailure::Invalid, $rejections['already seated']->failure);
        self::assertSame(AccountManagementFailure::Stale, $rejections['stale snapshot']->failure);
        self::assertNotNull($rejections['stale snapshot']->reload);
        self::assertSame(AccountManagementFailure::Invalid, $rejections['adviser position']->failure);
        self::assertArrayHasKey('position', $rejections['adviser position']->fieldErrors);

        self::assertSame($pivots, $this->pivotSnapshot());
        self::assertSame($users, $this->userSnapshot());
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_a_non_osas_actor_cannot_appoint_an_officer(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $organization = $this->eligibleOrganization();
        $candidate = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $result = $this->service()->appointOfficer($this->command(
            $actor,
            $organization,
            $candidate,
            OfficerPosition::President,
        ));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementFailure::Unauthorized, $result->failure);
        self::assertSame('student', $candidate->fresh()->role);
        self::assertNull($this->occupantOf($organization, 'President'));
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
        OfficerPosition $position,
    ): OrganizationAppointmentCommand {
        return OrganizationAppointmentCommand::forOfficer(
            new ActorAccountId((int) $actor->id),
            new OrganizationId((int) $organization->id),
            new ManagedAccountId((int) $candidate->id),
            $position,
            $this->snapshot($actor, $organization, $candidate, $position),
        );
    }

    private function snapshot(
        User $actor,
        Organization $organization,
        User $candidate,
        AdviserPosition|OfficerPosition $position,
    ): FreshnessSnapshot {
        $snapshot = $this->service()->currentFreshness(
            new ActorAccountId((int) $actor->id),
            new OrganizationId((int) $organization->id),
            new ManagedAccountId((int) $candidate->id),
            $position,
        );

        self::assertNotNull($snapshot);

        return $snapshot;
    }

    private function adviserSnapshot(User $actor, Organization $organization, User $candidate): FreshnessSnapshot
    {
        return $this->snapshot($actor, $organization, $candidate, AdviserPosition::SeniorAdviser);
    }

    private function missingCandidateSnapshot(User $actor, Organization $organization, int $candidateId): FreshnessSnapshot
    {
        $existing = $this->snapshot($actor, $organization, $actor, OfficerPosition::Comelec);

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

    private function slotCount(Organization $organization, string $role): int
    {
        return DB::table('organization_user')
            ->where('organization_id', $organization->id)
            ->where('membership_status', 'Active')
            ->where('role', $role)
            ->count();
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
