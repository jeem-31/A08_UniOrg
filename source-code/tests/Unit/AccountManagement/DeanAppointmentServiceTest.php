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
use App\Services\AccountManagement\CompletedAccountManagementCommand;
use App\Services\AccountManagement\DeanAppointmentCommand;
use App\Services\AccountManagement\DeanAppointmentService;
use App\Services\AccountManagement\DestructiveConfirmation;
use App\Services\AccountManagement\DestructiveConfirmationGuard;
use App\Services\AccountManagement\DestructiveConfirmationToken;
use App\Services\AccountManagement\DestructiveConfirmationVerifier;
use App\Services\AccountManagement\FreshnessSnapshot;
use App\Services\AccountManagement\FreshnessVerifier;
use App\Services\AccountManagement\ManagedAccountId;
use App\Services\AccountManagement\NonDeanOutcome;
use App\Services\AccountManagement\RejectedAccountManagementCommand;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** **Validates: Requirements 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 8.1, 8.4, 8.5, 8.6** */
final class DeanAppointmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private DestructiveConfirmationVerifier $confirmations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->confirmations = new DestructiveConfirmationVerifier(
            $this->app->make(Encrypter::class),
            $this->app->make(Repository::class),
        );
    }

    public function test_an_initial_appointment_promotes_the_candidate_and_records_one_audit_entry(): void
    {
        $actor = $this->actor();
        $candidate = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $pivots = $this->pivotSnapshot();

        $result = $this->service()->appoint(new DeanAppointmentCommand(
            new ActorAccountId($actor->id),
            new ManagedAccountId($candidate->id),
            $this->snapshot($actor, $candidate),
        ));

        self::assertInstanceOf(CompletedAccountManagementCommand::class, $result);
        self::assertSame('super_admin', $candidate->fresh()->role);
        self::assertTrue($candidate->fresh()->is_active);
        self::assertSame(1, $this->activeDeanCount());
        self::assertSame($pivots, $this->pivotSnapshot());

        $audit = AccountManagementAudit::query()->sole();
        self::assertSame(AccountManagementAudit::ACTION_DEAN_ASSIGNED, $audit->action);
        self::assertSame($actor->id, $audit->actor_user_id);
        self::assertSame($candidate->id, $audit->affected_user_id);
        self::assertSame($candidate->id, $audit->changes['incoming_dean']['id']);
        self::assertArrayNotHasKey('former_dean', $audit->changes);
        self::assertSame(['before' => 'admin', 'after' => 'super_admin'], $audit->changes['role']);
    }

    public function test_a_confirmed_replacement_deactivating_the_former_dean_assigns_the_student_role(): void
    {
        $actor = $this->actor();
        $formerDean = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $candidate = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $pivots = $this->pivotSnapshot([$formerDean, $candidate]);
        $outcome = NonDeanOutcome::deactivate();

        $result = $this->service()->appoint(new DeanAppointmentCommand(
            new ActorAccountId($actor->id),
            new ManagedAccountId($candidate->id),
            $this->snapshot($actor, $candidate, $formerDean),
            $outcome,
            $this->confirmationToken($actor, $candidate, $formerDean, $outcome),
        ));

        self::assertInstanceOf(CompletedAccountManagementCommand::class, $result);
        self::assertSame('super_admin', $candidate->fresh()->role);
        self::assertTrue($candidate->fresh()->is_active);
        self::assertSame('student', $formerDean->fresh()->role);
        self::assertFalse($formerDean->fresh()->is_active);
        self::assertSame(1, $this->activeDeanCount());
        self::assertSame($pivots, $this->pivotSnapshot());

        $audit = AccountManagementAudit::query()->sole();
        self::assertSame(AccountManagementAudit::ACTION_DEAN_REPLACED, $audit->action);
        self::assertSame($candidate->id, $audit->changes['incoming_dean']['id']);
        self::assertSame($formerDean->id, $audit->changes['former_dean']['id']);
        self::assertSame(['type' => 'deactivate', 'role' => null], $audit->changes['non_dean_outcome']);
    }

    public function test_a_confirmed_replacement_can_reassign_the_former_dean_to_a_selected_role(): void
    {
        $actor = $this->actor();
        $formerDean = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $candidate = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $outcome = NonDeanOutcome::reassign('admin');

        $result = $this->service()->appoint(new DeanAppointmentCommand(
            new ActorAccountId($actor->id),
            new ManagedAccountId($candidate->id),
            $this->snapshot($actor, $candidate, $formerDean),
            $outcome,
            $this->confirmationToken($actor, $candidate, $formerDean, $outcome),
        ));

        self::assertTrue($result->completed());
        self::assertSame('admin', $formerDean->fresh()->role);
        self::assertFalse($formerDean->fresh()->is_active);
        self::assertSame('super_admin', $candidate->fresh()->role);
        self::assertSame(1, $this->activeDeanCount());
        self::assertSame(
            ['type' => 'reassign', 'role' => 'admin'],
            AccountManagementAudit::query()->sole()->changes['non_dean_outcome'],
        );
    }

    public function test_invalid_targets_and_outcomes_change_no_dean_role_state_pivot_or_audit(): void
    {
        $actor = $this->actor();
        $formerDean = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $inactive = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        $candidate = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $missingId = User::query()->max('id') + 500;
        $outcome = NonDeanOutcome::deactivate();
        $service = $this->service();
        $pivots = $this->pivotSnapshot([$formerDean, $candidate, $inactive]);
        $users = $this->userSnapshot();

        $rejections = [
            'missing target' => $service->appoint(new DeanAppointmentCommand(
                new ActorAccountId($actor->id),
                new ManagedAccountId($missingId),
                $this->snapshot($actor, new ManagedAccountId($missingId), $formerDean),
                $outcome,
                $this->confirmationToken($actor, $candidate, $formerDean, $outcome),
            )),
            'inactive target' => $service->appoint(new DeanAppointmentCommand(
                new ActorAccountId($actor->id),
                new ManagedAccountId($inactive->id),
                $this->snapshot($actor, $inactive, $formerDean),
                $outcome,
                $this->confirmationToken($actor, $inactive, $formerDean, $outcome),
            )),
            'acting administrator' => $service->appoint(new DeanAppointmentCommand(
                new ActorAccountId($actor->id),
                new ManagedAccountId($actor->id),
                $this->snapshot($actor, $actor, $formerDean),
                $outcome,
                $this->confirmationToken($actor, $actor, $formerDean, $outcome),
            )),
            'missing outcome' => $service->appoint(new DeanAppointmentCommand(
                new ActorAccountId($actor->id),
                new ManagedAccountId($candidate->id),
                $this->snapshot($actor, $candidate, $formerDean),
            )),
            'missing confirmation' => $service->appoint(new DeanAppointmentCommand(
                new ActorAccountId($actor->id),
                new ManagedAccountId($candidate->id),
                $this->snapshot($actor, $candidate, $formerDean),
                $outcome,
            )),
            'mismatched confirmation' => $service->appoint(new DeanAppointmentCommand(
                new ActorAccountId($actor->id),
                new ManagedAccountId($candidate->id),
                $this->snapshot($actor, $candidate, $formerDean),
                $outcome,
                $this->confirmationToken($actor, $candidate, $formerDean, NonDeanOutcome::reassign('admin')),
            )),
            'stale snapshot' => $service->appoint(new DeanAppointmentCommand(
                new ActorAccountId($actor->id),
                new ManagedAccountId($candidate->id),
                $this->staleSnapshot($actor, $candidate, $formerDean),
                $outcome,
                $this->confirmationToken($actor, $candidate, $formerDean, $outcome),
            )),
        ];

        foreach ($rejections as $label => $rejection) {
            self::assertInstanceOf(RejectedAccountManagementCommand::class, $rejection, $label);
            self::assertFalse($rejection->completed(), $label);
        }

        self::assertSame(AccountManagementFailure::NotFound, $rejections['missing target']->failure);
        self::assertSame(AccountManagementFailure::Ineligible, $rejections['inactive target']->failure);
        self::assertSame(AccountManagementFailure::SelfAction, $rejections['acting administrator']->failure);
        self::assertSame(AccountManagementFailure::Invalid, $rejections['missing outcome']->failure);
        self::assertArrayHasKey('former_dean_outcome', $rejections['missing outcome']->fieldErrors);
        self::assertSame(AccountManagementFailure::Confirmation, $rejections['missing confirmation']->failure);
        self::assertSame(AccountManagementFailure::Confirmation, $rejections['mismatched confirmation']->failure);
        self::assertSame(AccountManagementFailure::Stale, $rejections['stale snapshot']->failure);
        self::assertNotNull($rejections['stale snapshot']->reload);

        self::assertSame($formerDean->id, User::query()
            ->where('role', 'super_admin')
            ->where('is_active', true)
            ->sole()
            ->id);
        self::assertSame($users, $this->userSnapshot());
        self::assertSame($pivots, $this->pivotSnapshot());
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_a_non_osas_actor_cannot_appoint_a_dean(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $candidate = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $result = $this->service()->appoint(new DeanAppointmentCommand(
            new ActorAccountId($actor->id),
            new ManagedAccountId($candidate->id),
            $this->snapshot($actor, $candidate),
        ));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementFailure::Unauthorized, $result->failure);
        self::assertSame('admin', $candidate->fresh()->role);
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    private function service(): DeanAppointmentService
    {
        return new DeanAppointmentService(
            $this->app->make(OsasAccountPolicy::class),
            new FreshnessVerifier($this->app->make(Encrypter::class)),
            new DestructiveConfirmationGuard($this->confirmations),
            new AccountManagementAuditWriter,
        );
    }

    private function actor(): User
    {
        return User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
    }

    private function snapshot(
        User $actor,
        User|ManagedAccountId $candidate,
        ?User $formerDean = null,
    ): FreshnessSnapshot {
        $candidateId = $candidate instanceof ManagedAccountId ? $candidate->value : (int) $candidate->id;

        return DeanAppointmentService::appointmentFreshness(
            new ActorAccountId((int) $actor->id),
            new ManagedAccountId($candidateId),
            $candidate instanceof User ? (int) $candidate->fresh()->management_version : 1,
            $formerDean?->id,
            $formerDean === null ? null : (int) $formerDean->fresh()->management_version,
        );
    }

    private function staleSnapshot(User $actor, User $candidate, User $formerDean): FreshnessSnapshot
    {
        return DeanAppointmentService::appointmentFreshness(
            new ActorAccountId((int) $actor->id),
            new ManagedAccountId((int) $candidate->id),
            (int) $candidate->fresh()->management_version,
            (int) $formerDean->id,
            (int) $formerDean->fresh()->management_version + 7,
        );
    }

    private function confirmationToken(
        User $actor,
        User $candidate,
        User $formerDean,
        NonDeanOutcome $outcome,
    ): DestructiveConfirmationToken {
        return DestructiveConfirmationToken::fromRequest($this->confirmations->issue(
            DestructiveConfirmation::forDeanReplacement(
                (int) $actor->id,
                (int) $candidate->id,
                (int) $candidate->fresh()->management_version,
                (int) $formerDean->id,
                (int) $formerDean->fresh()->management_version,
                $outcome,
            ),
        ));
    }

    /**
     * @param  list<User>  $members
     * @return list<array<string, mixed>>
     */
    private function pivotSnapshot(array $members = []): array
    {
        foreach ($members as $index => $member) {
            OrganizationUser::factory()->create([
                'user_id' => $member->id,
                'organization_id' => Organization::factory()->create()->id,
                'role' => $index % 2 === 0 ? 'President' : 'Senior Adviser',
            ]);
        }

        return DB::table('organization_user')->orderBy('id')->get()->map(
            static fn (object $row): array => (array) $row,
        )->all();
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

    private function activeDeanCount(): int
    {
        return User::query()->where('role', 'super_admin')->where('is_active', true)->count();
    }
}
