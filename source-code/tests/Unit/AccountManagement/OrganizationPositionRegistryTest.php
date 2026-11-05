<?php

namespace Tests\Unit\AccountManagement;

use App\Services\AccountManagement\AccountLifecycleAction;
use App\Services\AccountManagement\AccountManagementAction;
use App\Services\AccountManagement\AccountManagementFailure;
use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\AdviserPosition;
use App\Services\AccountManagement\AssignableNonDeanRole;
use App\Services\AccountManagement\ChangeAccountLifecycleCommand;
use App\Services\AccountManagement\CompletedAccountManagementCommand;
use App\Services\AccountManagement\CreateManagedAccountCommand;
use App\Services\AccountManagement\FreshnessRecord;
use App\Services\AccountManagement\FreshnessSlot;
use App\Services\AccountManagement\FreshnessSnapshot;
use App\Services\AccountManagement\ManagedAccountId;
use App\Services\AccountManagement\ManagedAccountProfile;
use App\Services\AccountManagement\NewAccountCredential;
use App\Services\AccountManagement\OfficerPosition;
use App\Services\AccountManagement\OrganizationAppointmentCommand;
use App\Services\AccountManagement\OrganizationId;
use App\Services\AccountManagement\OrganizationPosition;
use App\Services\AccountManagement\OrganizationPositionRegistry;
use App\Services\AccountManagement\RejectedAccountManagementCommand;
use PHPUnit\Framework\TestCase;

/** **Validates: Requirements 6.1, 6.2, 7.1, 7.2** */
final class OrganizationPositionRegistryTest extends TestCase
{
    public function test_registry_exposes_the_exact_canonical_adviser_and_officer_roles(): void
    {
        self::assertSame(['Senior Adviser', 'Junior Adviser'], OrganizationPositionRegistry::adviserRoles());
        self::assertSame([
            'President', 'Vice President', 'Secretary', 'Treasurer', 'Auditor',
            '1st Year Representative', '2nd Year Representative', '3rd Year Representative', '4th Year Representative',
            'Business Manager', 'Public Information Officer',
            'Event Coordinator', 'COMELEC',
        ], OrganizationPositionRegistry::officerRoles());
    }

    public function test_registry_resolves_only_exact_approved_route_values_to_typed_positions(): void
    {
        self::assertSame(AdviserPosition::SeniorAdviser, OrganizationPositionRegistry::adviserForRoute('senior-adviser'));
        self::assertNull(OrganizationPositionRegistry::positionForRoute('Senior Adviser'));
        self::assertNull(OrganizationPositionRegistry::positionForRoute('PRESIDENT'));
        self::assertNull(OrganizationPositionRegistry::positionForRoute('president '));
        self::assertNull(OrganizationPositionRegistry::officerForRoute('business-manager-2'));
        self::assertNull(OrganizationPositionRegistry::officerForRoute('public-information-officer-2'));
        self::assertNull(OrganizationPositionRegistry::officerForRoute('event-coordinator-2'));
    }

    public function test_organization_appointment_commands_are_typed_and_bound_to_their_signed_scope(): void
    {
        $actor = new ActorAccountId(10);
        $candidate = new ManagedAccountId(20);
        $organization = new OrganizationId(30);
        $adviser = OrganizationAppointmentCommand::forAdviser($actor, $organization, $candidate, AdviserPosition::SeniorAdviser, $this->appointmentSnapshot(AdviserPosition::SeniorAdviser));
        $officer = OrganizationAppointmentCommand::forOfficer($actor, $organization, $candidate, OfficerPosition::President, $this->appointmentSnapshot(OfficerPosition::President));

        self::assertTrue($adviser->isAdviser());
        self::assertFalse($officer->isAdviser());
        self::assertInstanceOf(OrganizationPosition::class, $adviser->position);
        self::assertSame('Senior Adviser', $adviser->position->role());
        self::assertSame('President', $officer->position->role());
    }

    public function test_appointment_command_rejects_a_snapshot_for_another_position(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OrganizationAppointmentCommand::forAdviser(
            new ActorAccountId(10), new OrganizationId(30), new ManagedAccountId(20),
            AdviserPosition::SeniorAdviser, $this->appointmentSnapshot(OfficerPosition::President),
        );
    }

    public function test_account_lifecycle_and_result_values_cannot_broaden_their_scope(): void
    {
        $actor = new ActorAccountId(10);
        $target = new ManagedAccountId(20);
        $snapshot = new FreshnessSnapshot('account.lifecycle', 10, 20, [
            FreshnessRecord::user(10, 1), FreshnessRecord::user(20, 1),
        ]);
        $create = new CreateManagedAccountCommand(
            $actor, ManagedAccountProfile::fromValidated(['email' => 'person@example.test']),
            NewAccountCredential::fromValidated('credential'), AssignableNonDeanRole::Student,
        );
        $lifecycle = new ChangeAccountLifecycleCommand($actor, $target, AccountLifecycleAction::Deactivate, $snapshot);
        $completed = new CompletedAccountManagementCommand(AccountManagementAction::AccountDeactivated, $target);
        $rejected = new RejectedAccountManagementCommand(AccountManagementFailure::Stale);

        self::assertSame(AssignableNonDeanRole::Student, $create->role);
        self::assertSame(AccountLifecycleAction::Deactivate, $lifecycle->action);
        self::assertTrue($completed->completed());
        self::assertFalse($rejected->completed());
        self::assertSame(AccountManagementFailure::Stale, $rejected->failure);
    }

    public function test_profile_and_lifecycle_commands_reject_unwhitelisted_or_self_scoped_input(): void
    {
        try {
            ManagedAccountProfile::fromValidated(['password' => 'not-allowed']);
            self::fail('A credential field must not be accepted as profile input.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        new ChangeAccountLifecycleCommand(
            new ActorAccountId(10), new ManagedAccountId(10), AccountLifecycleAction::Deactivate,
            new FreshnessSnapshot('account.lifecycle', 10, 10, [FreshnessRecord::user(10, 1)]),
        );
    }

    private function appointmentSnapshot(OrganizationPosition $position): FreshnessSnapshot
    {
        return new FreshnessSnapshot(
            'organization.appoint', 10, 20, [FreshnessRecord::user(10, 1), FreshnessRecord::user(20, 1)],
            FreshnessRecord::organization(30, 1), FreshnessSlot::empty(30, $position->role()),
        );
    }
}
