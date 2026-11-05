<?php

namespace Tests\Unit\AccountManagement;

use App\Models\AccountManagementAudit;
use App\Models\Organization;
use App\Models\User;
use App\Services\AccountManagement\AccountManagementAuditWriter;
use App\Services\AccountManagement\AdviserPosition;
use App\Services\AccountManagement\AssignableNonDeanRole;
use App\Services\AccountManagement\AuditIdentity;
use App\Services\AccountManagement\CompletedAction;
use App\Services\AccountManagement\NonDeanOutcome;
use App\Services\AccountManagement\OfficerPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/** **Validates: Requirements 3.7, 3.8, 4.9, 5.8, 6.5, 7.6, 9.2, 9.5** */
final class AccountManagementAuditWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_completed_profile_change_appends_exactly_one_credential_free_record(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin']);
        $target = User::factory()->create(['first_name' => 'Ada', 'course' => 'BSIT']);

        $audit = DB::transaction(fn (): AccountManagementAudit => $this->writer()->record(
            CompletedAction::profileUpdated(
                AuditIdentity::fromUser($actor),
                AuditIdentity::fromUser($target),
                ['first_name' => 'Ada', 'course' => 'BSIT'],
                ['first_name' => 'Grace', 'course' => 'BSIT'],
            ),
        ));

        self::assertSame(1, AccountManagementAudit::query()->count());
        self::assertSame(AccountManagementAudit::ACTION_PROFILE_UPDATED, $audit->action);
        self::assertSame($actor->id, $audit->actor_user_id);
        self::assertSame($target->id, $audit->affected_user_id);
        self::assertNull($audit->organization_id);
        self::assertSame(
            ['profile' => ['first_name' => ['before' => 'Ada', 'after' => 'Grace']]],
            $audit->changes,
        );
    }

    public function test_created_and_lifecycle_actions_record_only_allow_listed_role_and_state_values(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin']);
        $target = User::factory()->create(['role' => 'student']);
        $actorIdentity = AuditIdentity::fromUser($actor);
        $targetIdentity = AuditIdentity::fromUser($target);

        [$created, $deactivated] = DB::transaction(fn (): array => [
            $this->writer()->record(CompletedAction::accountCreated(
                $actorIdentity,
                $targetIdentity,
                ['first_name' => 'Grace', 'email' => 'grace@example.test'],
                AssignableNonDeanRole::Student,
            )),
            $this->writer()->record(CompletedAction::accountDeactivated($actorIdentity, $targetIdentity)),
        ]);
        self::assertSame([
            'profile' => [
                'email' => ['before' => null, 'after' => 'grace@example.test'],
                'first_name' => ['before' => null, 'after' => 'Grace'],
            ],
            'role' => ['before' => null, 'after' => 'student'],
            'state' => ['before' => null, 'after' => 'Active'],
        ], $created->changes);

        self::assertSame(
            ['state' => ['before' => 'Active', 'after' => 'Inactive']],
            $deactivated->changes,
        );
        self::assertSame(2, AccountManagementAudit::query()->count());
    }

    public function test_an_auto_expired_account_records_a_system_actor_and_active_to_inactive_state(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $audit = DB::transaction(fn (): AccountManagementAudit => $this->writer()->record(
            CompletedAction::accountExpired(AuditIdentity::fromUser($student)),
        ));

        self::assertSame(AccountManagementAudit::ACTION_ACCOUNT_EXPIRED, $audit->action);
        self::assertNull($audit->actor_user_id);
        self::assertSame('System (auto-expire)', $audit->actor_display);
        self::assertSame($student->id, $audit->affected_user_id);
        self::assertNull($audit->organization_id);
        self::assertSame(
            ['state' => ['before' => 'Active', 'after' => 'Inactive']],
            $audit->changes,
        );
    }

    public function test_dean_and_appointment_actions_record_identities_positions_and_outcomes(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin']);
        $incoming = User::factory()->create(['role' => 'admin']);
        $former = User::factory()->create(['role' => 'super_admin']);
        $organization = Organization::factory()->create();

        [$dean, $adviser, $officer] = DB::transaction(fn (): array => [
            $this->writer()->record(CompletedAction::deanReplaced(
                AuditIdentity::fromUser($actor),
                AuditIdentity::fromUser($incoming),
                'admin',
                AuditIdentity::fromUser($former),
                NonDeanOutcome::reassign('admin'),
            )),
            $this->writer()->record(CompletedAction::adviserAppointed(
                AuditIdentity::fromUser($actor),
                AuditIdentity::fromOrganization($organization),
                AdviserPosition::SeniorAdviser,
                null,
                AuditIdentity::fromUser($incoming),
                'student',
                'admin',
            )),
            $this->writer()->record(CompletedAction::officerAppointed(
                AuditIdentity::fromUser($actor),
                AuditIdentity::fromOrganization($organization),
                OfficerPosition::President,
                AuditIdentity::fromUser($former),
                AuditIdentity::fromUser($incoming),
                'admin',
                'admin',
            )),
        ]);

        self::assertSame(AccountManagementAudit::ACTION_DEAN_REPLACED, $dean->action);
        self::assertNull($dean->organization_id);
        self::assertSame($incoming->id, $dean->changes['incoming_dean']['id']);
        self::assertSame($former->id, $dean->changes['former_dean']['id']);
        self::assertSame(['type' => 'reassign', 'role' => 'admin'], $dean->changes['non_dean_outcome']);
        self::assertSame(['before' => 'admin', 'after' => 'super_admin'], $dean->changes['role']);

        self::assertSame($organization->id, $adviser->organization_id);
        self::assertSame($organization->name, $adviser->organization_display);
        self::assertSame('Senior Adviser', $adviser->changes['position']);
        self::assertArrayNotHasKey('former_assignee', $adviser->changes);
        self::assertSame(['before' => 'student', 'after' => 'admin'], $adviser->changes['role']);

        self::assertSame('President', $officer->changes['position']);
        self::assertSame($former->id, $officer->changes['former_assignee']['id']);
        self::assertSame($incoming->id, $officer->changes['selected_assignee']['id']);
    }

    public function test_credential_derived_payloads_are_rejected_before_any_record_is_written(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin']);
        $target = User::factory()->create();
        $rejected = 0;

        foreach ([
            ['password' => 'secret-value'],
            ['password_hash' => 'x'],
            ['remember_token' => 'x'],
            ['password_reset_sent_at' => 'x'],
            ['first_name' => '$2y$04$abcdefghijklmnopqrstuv'],
        ] as $hostilePatch) {
            try {
                DB::transaction(fn (): AccountManagementAudit => $this->writer()->record(
                    CompletedAction::profileUpdated(
                        AuditIdentity::fromUser($actor),
                        AuditIdentity::fromUser($target),
                        [],
                        $hostilePatch,
                    ),
                ));
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(5, $rejected);
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_a_completed_action_cannot_be_appended_outside_the_caller_transaction(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin']);
        $target = User::factory()->create();
        $action = CompletedAction::accountReactivated(
            AuditIdentity::fromUser($actor),
            AuditIdentity::fromUser($target),
        );

        $default = (string) config('database.default');
        config([
            'database.connections.audit_guard' => config("database.connections.{$default}"),
            'database.default' => 'audit_guard',
        ]);

        try {
            $this->writer()->record($action);
            self::fail('The writer must refuse to append outside the caller transaction.');
        } catch (RuntimeException $failure) {
            self::assertStringContainsString('caller transaction', $failure->getMessage());
        } finally {
            config(['database.default' => $default]);
        }

        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_a_rolled_back_transaction_leaves_no_audit_record(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin']);
        $target = User::factory()->create();

        try {
            DB::transaction(function () use ($actor, $target): void {
                $this->writer()->record(CompletedAction::accountDeactivated(
                    AuditIdentity::fromUser($actor),
                    AuditIdentity::fromUser($target),
                ));

                throw new RuntimeException('persistence failure');
            });
        } catch (RuntimeException) {
            // The caller maps this to a safe failure result.
        }

        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_a_deletion_record_retains_a_redacted_snapshot_after_the_user_is_removed(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin']);
        $target = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $targetId = $target->id;

        DB::transaction(function () use ($actor, $target): void {
            $this->writer()->record(CompletedAction::accountDeleted(
                AuditIdentity::fromUser($actor),
                AuditIdentity::fromUser($target),
            ));

            $target->delete();
        });

        $audit = AccountManagementAudit::query()->sole();

        self::assertSame(AccountManagementAudit::ACTION_ACCOUNT_DELETED, $audit->action);
        self::assertNull($audit->affected_user_id);
        self::assertSame('Ada Lovelace', $audit->affected_display);
        self::assertSame($targetId, $audit->changes['deleted_account']['id']);
        self::assertSame('Ada Lovelace', $audit->changes['deleted_account']['display']);
    }

    private function writer(): AccountManagementAuditWriter
    {
        return new AccountManagementAuditWriter;
    }
}
