<?php

namespace Tests\Feature;

use App\Console\Commands\EnsureAnnualMembershipFees;
use App\Events\MembershipFeeEligibilityUpdated;
use App\Http\Controllers\Admin\PaymentController;
use App\Models\Department;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\StudentPayment;
use App\Models\User;
use App\Services\MembershipFeeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\RealBroadcastAuth;
use Tests\TestCase;

class MembershipFeeWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use RealBroadcastAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useRealBroadcastAuth();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-03 10:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_only_usable_mother_organizations_receive_a_protected_annual_fee(): void
    {
        $activeMother = $this->organization('Mother Org', 'Approved');
        $this->organization('Sub-Org', 'Approved');
        $this->organization('Mother Org', 'Inactive');

        $fees = app(MembershipFeeService::class)->ensureForActiveMotherOrganizations();

        $this->assertCount(1, $fees);
        $this->assertDatabaseHas('payments', [
            'organization_id' => $activeMother->id,
            'title' => 'Membership Fee',
            'category' => 'membership_fee',
            'amount' => 250.00,
            'due_date' => '2026-08-01 00:00:00',
            'membership_cycle_start' => '2026-08-01 00:00:00',
            'is_required_membership_fee' => true,
            'user_id' => null,
        ]);
    }

    public function test_annual_command_is_idempotent_and_preserves_previous_cycle_records(): void
    {
        $mother = $this->organization('Mother Org', 'Approved');

        Artisan::call(EnsureAnnualMembershipFees::class, ['--date' => '2026-08-03']);
        Artisan::call(EnsureAnnualMembershipFees::class, ['--date' => '2026-08-03']);
        Artisan::call(EnsureAnnualMembershipFees::class, ['--date' => '2027-08-01']);

        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('payments', [
            'organization_id' => $mother->id,
            'membership_cycle_start' => '2026-08-01 00:00:00',
            'is_required_membership_fee' => true,
        ]);
        $this->assertDatabaseHas('payments', [
            'organization_id' => $mother->id,
            'membership_cycle_start' => '2027-08-01 00:00:00',
            'is_required_membership_fee' => true,
        ]);
    }

    public function test_protected_membership_fee_cannot_be_deleted_even_with_a_direct_json_request(): void
    {
        $mother = $this->organization('Mother Org', 'Approved');
        $fee = app(MembershipFeeService::class)->ensureForOrganization($mother);
        $request = Request::create('/admin/payments/announcements/'.$fee->id, 'DELETE', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        $response = app(PaymentController::class)->deleteAnnouncement($request, $fee);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('membership_fee_protected', $response->getData(true)['code']);
        $this->assertDatabaseHas('payments', ['id' => $fee->id]);
    }

    public function test_student_can_register_for_related_sub_org_event_after_paying_mother_org_fee(): void
    {
        $mother = $this->organization('Mother Org', 'Approved', 88);
        $subOrganization = $this->organization('Sub-Org', 'Approved', 88);
        $student = User::factory()->create(['role' => 'student']);
        $student->organizations()->attach($mother->id, ['role' => 'Student', 'membership_status' => 'Active']);
        $fee = app(MembershipFeeService::class)->ensureForOrganization($mother);
        $fee->update(['gcash_number' => '09123456789']);
        $this->approvedMembershipPayment($student, $fee);
        $event = $this->eventFor($subOrganization, $student);

        $response = $this->actingAs($student)
            ->withSession(['current_organization_id' => $mother->id])
            ->postJson(route('events.register', $event));

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'user_id' => $student->id,
            'status' => 'registered',
        ]);
    }

    public function test_unpaid_or_pending_membership_fee_cannot_register_and_returns_a_clear_json_code(): void
    {
        $mother = $this->organization('Mother Org', 'Approved');
        $student = User::factory()->create(['role' => 'student']);
        $student->organizations()->attach($mother->id, ['role' => 'Student', 'membership_status' => 'Active']);
        $fee = app(MembershipFeeService::class)->ensureForOrganization($mother);
        $fee->update(['gcash_number' => '09123456789']);
        $event = $this->eventFor($mother, $student);

        $this->actingAs($student)
            ->withSession(['current_organization_id' => $mother->id])
            ->postJson(route('events.register', $event))
            ->assertForbidden()
            ->assertJsonPath('code', 'membership_fee_unpaid')
            ->assertJsonPath('payments_url', route('payments.index'));

        StudentPayment::create([
            'organization_id' => $mother->id,
            'user_id' => $student->id,
            'payment_id' => $fee->id,
            'transaction_id' => 'PENDING-MEMBERSHIP-1',
            'category' => 'membership_fee',
            'amount' => $fee->amount,
            'payment_method' => 'gcash',
            'status' => 'pending',
            'verification_status' => 'pending',
        ]);

        $this->actingAs($student)
            ->withSession(['current_organization_id' => $mother->id])
            ->postJson(route('events.register', $event))
            ->assertForbidden()
            ->assertJsonPath('code', 'membership_fee_pending');
    }

    public function test_membership_fee_submission_uses_the_server_amount_not_a_forged_client_amount(): void
    {
        $mother = $this->organization('Mother Org', 'Approved');
        $student = User::factory()->create(['role' => 'student']);
        $student->organizations()->attach($mother->id, ['role' => 'Student', 'membership_status' => 'Active']);
        $fee = app(MembershipFeeService::class)->ensureForOrganization($mother);
        $fee->update(['gcash_number' => '09123456789']);

        $this->actingAs($student)
            ->withSession(['current_organization_id' => $mother->id])
            ->postJson(route('payments.gcash.create-pending'), [
                'payment_id' => $fee->id,
                'amount' => '0.01',
                'title' => 'Forged Membership Fee',
            ])
            ->assertOk();

        $this->assertDatabaseHas('student_payments', [
            'user_id' => $student->id,
            'payment_id' => $fee->id,
            'amount' => 250.00,
            'category' => 'membership_fee',
            'verification_status' => 'pending',
        ]);
    }

    public function test_membership_fee_private_channel_only_authorizes_the_matching_student(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-membership-fee.'.$student->id,
            ])
            ->assertOk();

        $this->actingAs($otherStudent)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-membership-fee.'.$student->id,
            ])
            ->assertForbidden();
    }

    public function test_membership_eligibility_event_uses_the_students_private_channel(): void
    {
        $event = new MembershipFeeEligibilityUpdated(55, true, 91);

        $this->assertSame('private-membership-fee.55', $event->broadcastOn()[0]->name);
        $this->assertSame('membership-fee-eligibility-updated', $event->broadcastAs());
        $this->assertSame(['eligible' => true, 'membership_payment_id' => 91], $event->broadcastWith());
    }

    private function organization(string $type, string $status, ?int $departmentId = null): Organization
    {
        if ($departmentId !== null && ! Department::whereKey($departmentId)->exists()) {
            $department = new Department([
                'department_name' => 'Department '.$departmentId,
                'department_acronym' => 'D'.$departmentId,
            ]);
            $department->id = $departmentId;
            $department->save();
        }

        return Organization::create([
            'name' => fake()->unique()->company(),
            'type' => $type,
            'status' => $status,
            'department_id' => $departmentId,
        ]);
    }

    private function eventFor(Organization $organization, User $user): Event
    {
        return Event::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'title' => 'Membership-gated event',
            'description' => 'Event description',
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->addHours(2),
            'venue' => 'Activity Center (AC)',
            'is_published' => true,
            'status' => 'published',
            'approval_status' => 'approved',
        ]);
    }

    private function approvedMembershipPayment(User $student, Payment $fee): StudentPayment
    {
        return StudentPayment::create([
            'organization_id' => $fee->organization_id,
            'user_id' => $student->id,
            'payment_id' => $fee->id,
            'transaction_id' => 'APPROVED-MEMBERSHIP-'.str()->random(8),
            'category' => 'membership_fee',
            'amount' => $fee->amount,
            'payment_method' => 'manual',
            'status' => 'completed',
            'verification_status' => 'approved',
            'paid_at' => now(),
        ]);
    }
}
