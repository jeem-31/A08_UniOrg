<?php

namespace Tests\Unit\AccountManagement;

use App\Models\AccountManagementAudit;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Policies\OsasAccountPolicy;
use App\Services\AccountManagement\AccountLifecycleAction;
use App\Services\AccountManagement\AccountManagementAction;
use App\Services\AccountManagement\AccountManagementAuditWriter;
use App\Services\AccountManagement\AccountManagementFailure;
use App\Services\AccountManagement\AccountManagementService;
use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\AssignableNonDeanRole;
use App\Services\AccountManagement\AssociatedRecordInspector;
use App\Services\AccountManagement\ChangeAccountLifecycleCommand;
use App\Services\AccountManagement\ChangeNonDeanRoleCommand;
use App\Services\AccountManagement\CompletedAccountManagementCommand;
use App\Services\AccountManagement\CreateManagedAccountCommand;
use App\Services\AccountManagement\DeleteManagedAccountCommand;
use App\Services\AccountManagement\DestructiveConfirmation;
use App\Services\AccountManagement\DestructiveConfirmationGuard;
use App\Services\AccountManagement\DestructiveConfirmationToken;
use App\Services\AccountManagement\DestructiveConfirmationVerifier;
use App\Services\AccountManagement\FreshnessVerifier;
use App\Services\AccountManagement\ManagedAccountId;
use App\Services\AccountManagement\ManagedAccountProfile;
use App\Services\AccountManagement\ManagedAccountValidator;
use App\Services\AccountManagement\NewAccountCredential;
use App\Services\AccountManagement\RejectedAccountManagementCommand;
use App\Services\AccountManagement\StudentAccountValidity;
use App\Services\AccountManagement\UpdateManagedAccountProfileCommand;
use App\Services\MembershipFeeService;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 4.1, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9, 8.4, 8.5, 8.6, 9.5** */
final class AccountManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private ?DestructiveConfirmationVerifier $confirmations = null;

    public function test_it_creates_an_active_non_dean_account_with_a_hashed_credential_and_one_audit(): void
    {
        $actor = $this->actor();

        $result = $this->service()->create(new CreateManagedAccountCommand(
            new ActorAccountId((int) $actor->id),
            ManagedAccountProfile::fromValidated([
                'first_name' => 'Grace',
                'last_name' => 'Hopper',
                'email' => 'grace.hopper@example.test',
                'student_id' => '2026-00011',
            ]),
            NewAccountCredential::fromValidated('correct-horse-battery'),
            AssignableNonDeanRole::Admin,
        ));

        self::assertInstanceOf(CompletedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementAction::AccountCreated, $result->action);

        $created = User::query()->findOrFail($result->affectedAccount->value);

        self::assertTrue((bool) $created->is_active);
        self::assertSame('admin', $created->role);
        self::assertSame('grace.hopper@example.test', $created->email);
        self::assertNotSame('correct-horse-battery', $created->password);
        self::assertTrue(Hash::check('correct-horse-battery', $created->password));

        $audit = AccountManagementAudit::query()->sole();

        self::assertSame(AccountManagementAudit::ACTION_ACCOUNT_CREATED, $audit->action);
        self::assertSame($created->id, $audit->affected_user_id);
        self::assertSame(['before' => null, 'after' => 'admin'], $audit->changes['role']);
        self::assertSame(['before' => null, 'after' => 'Active'], $audit->changes['state']);
        self::assertArrayNotHasKey('password', $audit->changes);
        self::assertStringNotContainsString('correct-horse-battery', json_encode($audit->changes));
    }

    public function test_it_rejects_an_invalid_or_duplicate_creation_atomically(): void
    {
        $actor = $this->actor();
        $existing = User::factory()->create(['email' => 'taken@example.test', 'student_id' => '2026-00099']);
        $countBefore = User::query()->count();

        $invalid = $this->service()->create(new CreateManagedAccountCommand(
            new ActorAccountId((int) $actor->id),
            ManagedAccountProfile::fromValidated(['first_name' => 'No', 'email' => 'not-an-email']),
            NewAccountCredential::fromValidated('short'),
            AssignableNonDeanRole::Student,
        ));

        $duplicate = $this->service()->create(new CreateManagedAccountCommand(
            new ActorAccountId((int) $actor->id),
            ManagedAccountProfile::fromValidated([
                'first_name' => 'Copy',
                'last_name' => 'Cat',
                'email' => 'TAKEN@example.test',
                'student_id' => '2026-00099',
            ]),
            NewAccountCredential::fromValidated('correct-horse-battery'),
            AssignableNonDeanRole::Student,
        ));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $invalid);
        self::assertSame(AccountManagementFailure::Invalid, $invalid->failure);
        self::assertSame(['email', 'last_name', 'password'], $this->sortedFields($invalid));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $duplicate);
        self::assertSame(AccountManagementFailure::Conflict, $duplicate->failure);
        self::assertSame(['email', 'student_id'], $this->sortedFields($duplicate));

        self::assertSame($countBefore, User::query()->count());
        self::assertSame(0, AccountManagementAudit::query()->count());
        self::assertSame('taken@example.test', $existing->fresh()->email);
    }

    public function test_it_patches_only_submitted_profile_fields_and_audits_the_exact_diff(): void
    {
        $actor = $this->actor();
        $target = User::factory()->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'course' => 'BSIT',
            'role' => 'student',
        ]);
        $originalEmail = $target->email;

        $result = $this->service()->updateProfile(new UpdateManagedAccountProfileCommand(
            new ActorAccountId((int) $actor->id),
            new ManagedAccountId((int) $target->id),
            ManagedAccountProfile::fromValidated(['first_name' => 'Augusta', 'course' => 'BSCS']),
            AccountManagementService::profileFreshness(
                new ActorAccountId((int) $actor->id),
                new ManagedAccountId((int) $target->id),
                (int) $target->management_version,
            ),
        ));

        $target->refresh();

        self::assertInstanceOf(CompletedAccountManagementCommand::class, $result);
        self::assertSame('Augusta', $target->first_name);
        self::assertSame('BSCS', $target->course);
        self::assertSame('Lovelace', $target->last_name);
        self::assertSame($originalEmail, $target->email);
        self::assertSame('student', $target->role);

        $audit = AccountManagementAudit::query()->sole();

        self::assertSame(AccountManagementAudit::ACTION_PROFILE_UPDATED, $audit->action);
        self::assertSame([
            'profile' => [
                'course' => ['before' => 'BSIT', 'after' => 'BSCS'],
                'first_name' => ['before' => 'Ada', 'after' => 'Augusta'],
            ],
        ], $audit->changes);
    }

    public function test_it_rejects_a_stale_profile_submission_without_changing_any_value(): void
    {
        $actor = $this->actor();
        $target = User::factory()->create(['first_name' => 'Ada']);
        $issued = AccountManagementService::profileFreshness(
            new ActorAccountId((int) $actor->id),
            new ManagedAccountId((int) $target->id),
            (int) $target->management_version,
        );

        $target->forceFill(['section' => 'Z'])->save();

        $result = $this->service()->updateProfile(new UpdateManagedAccountProfileCommand(
            new ActorAccountId((int) $actor->id),
            new ManagedAccountId((int) $target->id),
            ManagedAccountProfile::fromValidated(['first_name' => 'Augusta']),
            $issued,
        ));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementFailure::Stale, $result->failure);
        self::assertNotNull($result->reload);
        self::assertSame('Ada', $target->fresh()->first_name);
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_it_changes_only_the_role_for_a_non_dean_account(): void
    {
        $actor = $this->actor();
        $target = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $result = $this->service()->changeNonDeanRole(new ChangeNonDeanRoleCommand(
            new ActorAccountId((int) $actor->id),
            new ManagedAccountId((int) $target->id),
            AssignableNonDeanRole::OsasAdmin,
            AccountManagementService::roleFreshness(
                new ActorAccountId((int) $actor->id),
                new ManagedAccountId((int) $target->id),
                (int) $target->management_version,
            ),
        ));

        $email = $target->email;
        $target->refresh();

        self::assertInstanceOf(CompletedAccountManagementCommand::class, $result);
        self::assertSame('osas_admin', $target->role);
        self::assertTrue((bool) $target->is_active);
        self::assertSame($email, $target->email);

        $audit = AccountManagementAudit::query()->sole();

        self::assertSame(AccountManagementAudit::ACTION_ROLE_CHANGED, $audit->action);
        self::assertSame(['before' => 'student', 'after' => 'osas_admin'], $audit->changes['role']);
    }

    public function test_it_rejects_an_ordinary_role_change_for_the_current_dean(): void
    {
        $actor = $this->actor();
        $dean = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $result = $this->service()->changeNonDeanRole(new ChangeNonDeanRoleCommand(
            new ActorAccountId((int) $actor->id),
            new ManagedAccountId((int) $dean->id),
            AssignableNonDeanRole::Student,
            AccountManagementService::roleFreshness(
                new ActorAccountId((int) $actor->id),
                new ManagedAccountId((int) $dean->id),
                (int) $dean->management_version,
            ),
        ));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementFailure::Ineligible, $result->failure);
        self::assertSame('super_admin', $dean->fresh()->role);
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_it_denies_a_command_from_an_actor_without_active_osas_access(): void
    {
        $inactiveOsas = User::factory()->create(['role' => 'osas_admin', 'is_active' => false]);
        $target = User::factory()->create(['role' => 'student', 'first_name' => 'Ada']);

        $result = $this->service()->updateProfile(new UpdateManagedAccountProfileCommand(
            new ActorAccountId((int) $inactiveOsas->id),
            new ManagedAccountId((int) $target->id),
            ManagedAccountProfile::fromValidated(['first_name' => 'Augusta']),
            AccountManagementService::profileFreshness(
                new ActorAccountId((int) $inactiveOsas->id),
                new ManagedAccountId((int) $target->id),
                (int) $target->management_version,
            ),
        ));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementFailure::Unauthorized, $result->failure);
        self::assertSame('Ada', $target->fresh()->first_name);
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_it_reports_a_missing_target_without_touching_other_accounts(): void
    {
        $actor = $this->actor();
        $other = User::factory()->create(['first_name' => 'Ada']);
        $missingId = ((int) User::query()->max('id')) + 500;

        $result = $this->service()->updateProfile(new UpdateManagedAccountProfileCommand(
            new ActorAccountId((int) $actor->id),
            new ManagedAccountId($missingId),
            ManagedAccountProfile::fromValidated(['first_name' => 'Augusta']),
            AccountManagementService::profileFreshness(
                new ActorAccountId((int) $actor->id),
                new ManagedAccountId($missingId),
                1,
            ),
        ));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementFailure::NotFound, $result->failure);
        self::assertSame('Ada', $other->fresh()->first_name);
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_it_deactivates_another_account_by_changing_only_its_state(): void
    {
        $actor = $this->actor();
        $target = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $before = $this->accountSnapshot($target);

        $result = $this->service()->changeActivation($this->lifecycleCommand(
            $actor,
            $target,
            AccountLifecycleAction::Deactivate,
        ));

        $target->refresh();

        self::assertInstanceOf(CompletedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementAction::AccountDeactivated, $result->action);
        self::assertFalse((bool) $target->is_active);
        self::assertSame(
            array_merge($before, ['is_active' => false]),
            $this->accountSnapshot($target),
        );

        $audit = AccountManagementAudit::query()->sole();

        self::assertSame(AccountManagementAudit::ACTION_ACCOUNT_DEACTIVATED, $audit->action);
        self::assertSame($actor->id, $audit->actor_user_id);
        self::assertSame($target->id, $audit->affected_user_id);
        self::assertSame(['before' => 'Active', 'after' => 'Inactive'], $audit->changes['state']);
        self::assertSame(['state'], array_keys($audit->changes));
    }

    public function test_it_reactivates_an_account_without_changing_its_role_or_assignments(): void
    {
        $actor = $this->actor();
        $target = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        $organization = Organization::factory()->create();
        OrganizationUser::factory()->create([
            'user_id' => $target->id,
            'organization_id' => $organization->id,
            'role' => 'Senior Adviser',
        ]);
        $pivots = $this->pivotRows();
        $before = $this->accountSnapshot($target);

        $result = $this->service()->changeActivation($this->lifecycleCommand(
            $actor,
            $target,
            AccountLifecycleAction::Activate,
        ));

        $target->refresh();

        self::assertInstanceOf(CompletedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementAction::AccountReactivated, $result->action);
        self::assertTrue((bool) $target->is_active);
        self::assertSame('admin', $target->role);
        self::assertSame($pivots, $this->pivotRows());
        self::assertSame(
            array_merge($before, ['is_active' => true]),
            $this->accountSnapshot($target),
        );

        $audit = AccountManagementAudit::query()->sole();

        self::assertSame(AccountManagementAudit::ACTION_ACCOUNT_REACTIVATED, $audit->action);
        self::assertSame(['before' => 'Inactive', 'after' => 'Active'], $audit->changes['state']);
    }

    public function test_it_refuses_to_reactivate_a_student_whose_validity_window_has_fully_closed(): void
    {
        $actor = $this->actor();
        $target = User::factory()->create([
            'role' => 'student',
            'is_active' => false,
            'expires_at' => now()->subMonths(StudentAccountValidity::GRACE_MONTHS + 1),
        ]);

        $result = $this->service()->changeActivation($this->lifecycleCommand(
            $actor,
            $target,
            AccountLifecycleAction::Activate,
        ));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementFailure::Ineligible, $result->failure);
        self::assertSame(['account'], array_keys($result->fieldErrors));
        self::assertFalse((bool) $target->fresh()->is_active);
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_it_still_reactivates_a_student_inside_the_grace_window(): void
    {
        $actor = $this->actor();
        $target = User::factory()->create([
            'role' => 'student',
            'is_active' => false,
            'expires_at' => now()->subDay(),
        ]);

        $result = $this->service()->changeActivation($this->lifecycleCommand(
            $actor,
            $target,
            AccountLifecycleAction::Activate,
        ));

        self::assertInstanceOf(CompletedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementAction::AccountReactivated, $result->action);
        self::assertTrue((bool) $target->fresh()->is_active);

        $audit = AccountManagementAudit::query()->sole();
        self::assertSame(AccountManagementAudit::ACTION_ACCOUNT_REACTIVATED, $audit->action);
    }

    public function test_it_rejects_a_stale_lifecycle_submission_without_changing_state(): void
    {
        $actor = $this->actor();
        $target = User::factory()->create(['is_active' => true]);
        $issued = AccountManagementService::lifecycleFreshness(
            new ActorAccountId((int) $actor->id),
            new ManagedAccountId((int) $target->id),
            (int) $target->management_version,
        );

        $target->forceFill(['section' => 'Z'])->save();

        $result = $this->service()->changeActivation(new ChangeAccountLifecycleCommand(
            new ActorAccountId((int) $actor->id),
            new ManagedAccountId((int) $target->id),
            AccountLifecycleAction::Deactivate,
            $issued,
        ));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementFailure::Stale, $result->failure);
        self::assertNotNull($result->reload);
        self::assertTrue((bool) $target->fresh()->is_active);
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_a_lifecycle_or_deletion_command_cannot_target_the_acting_administrator(): void
    {
        $actor = $this->actor();
        $actorId = new ActorAccountId((int) $actor->id);
        $selfTarget = new ManagedAccountId((int) $actor->id);
        $snapshot = AccountManagementService::lifecycleFreshness($actorId, $selfTarget, (int) $actor->management_version);

        $lifecycle = null;

        try {
            new ChangeAccountLifecycleCommand($actorId, $selfTarget, AccountLifecycleAction::Deactivate, $snapshot);
        } catch (\InvalidArgumentException $rejected) {
            $lifecycle = $rejected;
        }

        $deletion = null;

        try {
            new DeleteManagedAccountCommand(
                $actorId,
                $selfTarget,
                AccountManagementService::deletionFreshness($actorId, $selfTarget, (int) $actor->management_version),
                DestructiveConfirmationToken::fromRequest('irrelevant-token'),
            );
        } catch (\InvalidArgumentException $rejected) {
            $deletion = $rejected;
        }

        self::assertNotNull($lifecycle);
        self::assertNotNull($deletion);
        self::assertTrue((bool) $actor->fresh()->is_active);
        self::assertNotNull(User::query()->find($actor->id));
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_it_permanently_deletes_an_eligible_confirmed_account_and_retains_a_redacted_audit(): void
    {
        $actor = $this->actor();
        $target = User::factory()->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'is_active' => false,
        ]);
        $targetId = (int) $target->id;
        $token = $this->deletionToken($actor, $target);

        $result = $this->service()->delete(new DeleteManagedAccountCommand(
            new ActorAccountId((int) $actor->id),
            new ManagedAccountId($targetId),
            AccountManagementService::deletionFreshness(
                new ActorAccountId((int) $actor->id),
                new ManagedAccountId($targetId),
                (int) $target->management_version,
            ),
            $token,
        ));

        self::assertInstanceOf(CompletedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementAction::AccountDeleted, $result->action);
        self::assertNull(User::query()->find($targetId));

        $audit = AccountManagementAudit::query()->sole();

        self::assertSame(AccountManagementAudit::ACTION_ACCOUNT_DELETED, $audit->action);
        self::assertNull($audit->affected_user_id);
        self::assertSame('Ada Lovelace', $audit->affected_display);
        self::assertSame('Ada Lovelace', $audit->changes['deleted_account']['display']);
        self::assertStringNotContainsString('password', json_encode($audit->changes));
    }

    public function test_it_refuses_to_delete_an_account_with_associated_records(): void
    {
        $actor = $this->actor();
        $target = User::factory()->create();
        OrganizationUser::factory()->create([
            'user_id' => $target->id,
            'organization_id' => Organization::factory()->create()->id,
            'role' => 'President',
        ]);
        $pivots = $this->pivotRows();

        $result = $this->service()->delete(new DeleteManagedAccountCommand(
            new ActorAccountId((int) $actor->id),
            new ManagedAccountId((int) $target->id),
            AccountManagementService::deletionFreshness(
                new ActorAccountId((int) $actor->id),
                new ManagedAccountId((int) $target->id),
                (int) $target->management_version,
            ),
            $this->deletionToken($actor, $target),
        ));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementFailure::Ineligible, $result->failure);
        self::assertSame(['account'], array_keys($result->fieldErrors));
        self::assertStringNotContainsString('organization_user', $result->fieldErrors['account'][0]);
        self::assertNotNull(User::query()->find($target->id));
        self::assertSame($pivots, $this->pivotRows());
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_it_refuses_to_delete_without_a_valid_confirmation(): void
    {
        $actor = $this->actor();
        $target = User::factory()->create();
        $reused = $this->deletionToken($actor, $target);
        $service = $this->service();

        $command = fn (DestructiveConfirmationToken $token): DeleteManagedAccountCommand => new DeleteManagedAccountCommand(
            new ActorAccountId((int) $actor->id),
            new ManagedAccountId((int) $target->id),
            AccountManagementService::deletionFreshness(
                new ActorAccountId((int) $actor->id),
                new ManagedAccountId((int) $target->id),
                (int) $target->fresh()->management_version,
            ),
            $token,
        );

        $mismatched = $service->delete($command(DestructiveConfirmationToken::fromRequest('not-a-real-token')));

        self::assertInstanceOf(CompletedAccountManagementCommand::class, $service->delete($command($reused)));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $mismatched);
        self::assertSame(AccountManagementFailure::Confirmation, $mismatched->failure);
        self::assertSame(1, AccountManagementAudit::query()->count());
    }

    public function test_it_rejects_deletion_requested_by_an_actor_without_active_osas_access(): void
    {
        $inactiveOsas = User::factory()->create(['role' => 'osas_admin', 'is_active' => false]);
        $target = User::factory()->create();

        $result = $this->service()->delete(new DeleteManagedAccountCommand(
            new ActorAccountId((int) $inactiveOsas->id),
            new ManagedAccountId((int) $target->id),
            AccountManagementService::deletionFreshness(
                new ActorAccountId((int) $inactiveOsas->id),
                new ManagedAccountId((int) $target->id),
                (int) $target->management_version,
            ),
            $this->deletionToken($inactiveOsas, $target),
        ));

        self::assertInstanceOf(RejectedAccountManagementCommand::class, $result);
        self::assertSame(AccountManagementFailure::Unauthorized, $result->failure);
        self::assertNotNull(User::query()->find($target->id));
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    private function lifecycleCommand(
        User $actor,
        User $target,
        AccountLifecycleAction $action,
    ): ChangeAccountLifecycleCommand {
        return new ChangeAccountLifecycleCommand(
            new ActorAccountId((int) $actor->id),
            new ManagedAccountId((int) $target->id),
            $action,
            AccountManagementService::lifecycleFreshness(
                new ActorAccountId((int) $actor->id),
                new ManagedAccountId((int) $target->id),
                (int) $target->management_version,
            ),
        );
    }

    /** @return array<string, mixed> */
    private function accountSnapshot(User $user): array
    {
        $row = (array) DB::table('users')->where('id', $user->getKey())->sole();

        unset($row['management_version'], $row['updated_at']);

        $row['is_active'] = (bool) $row['is_active'];

        return $row;
    }

    /** @return list<array<string, mixed>> */
    private function pivotRows(): array
    {
        return DB::table('organization_user')
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    private function actor(): User
    {
        return User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
    }

    /** @return list<string> */
    private function sortedFields(RejectedAccountManagementCommand $rejected): array
    {
        $fields = array_keys($rejected->fieldErrors);
        sort($fields);

        return $fields;
    }

    private function service(): AccountManagementService
    {
        return new AccountManagementService(
            new FreshnessVerifier($this->app->make(Encrypter::class)),
            new AccountManagementAuditWriter,
            new ManagedAccountValidator,
            new OsasAccountPolicy,
            new AssociatedRecordInspector($this->app->make(DatabaseManager::class)),
            new DestructiveConfirmationGuard($this->confirmations()),
            new StudentAccountValidity(new MembershipFeeService),
        );
    }

    private function confirmations(): DestructiveConfirmationVerifier
    {
        return $this->confirmations ??= new DestructiveConfirmationVerifier(
            $this->app->make(Encrypter::class),
            $this->app->make(Repository::class),
        );
    }

    private function deletionToken(User $actor, User $target): DestructiveConfirmationToken
    {
        return DestructiveConfirmationToken::fromRequest($this->confirmations()->issue(
            DestructiveConfirmation::forAccountDeletion(
                (int) $actor->id,
                (int) $target->id,
                (int) $target->management_version,
            ),
        ));
    }
}
