<?php

namespace Tests\Feature;

use App\Models\ApplyOrg;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** **Validates: global accreditation school year period (start + end) set by OSAS for all approved organizations** */
final class OsasAccreditationExpirationTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('apply_orgs');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_osas_admin_sets_one_global_period_for_approved_organizations_only(): void
    {
        $approved = Organization::create([
            'name' => 'Approved Org A',
            'org_acronym' => 'AOA',
            'type' => 'Mother Org',
            'status' => 'Approved',
        ]);
        $approvedTwo = Organization::create([
            'name' => 'Approved Org B',
            'org_acronym' => 'AOB',
            'type' => 'Sub Org',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subMonths(6),
            'accreditation_expires_at' => now()->addDays(90),
        ]);
        $pending = Organization::create([
            'name' => 'Pending Org',
            'org_acronym' => 'PO',
            'type' => 'Mother Org',
            'status' => 'Pending',
            'accreditation_started_at' => now()->subMonths(3),
            'accreditation_expires_at' => now()->addDays(10),
        ]);

        $startedAt = now()->subDays(60)->startOfDay();
        $expiresAt = now()->addYear()->startOfDay();

        $response = $this->actingAs($this->user('osas_admin'))->postJson(
            route('osas.settings.accreditation.update'),
            [
                'accreditation_start_date' => $startedAt->format('Y-m-d'),
                'accreditation_end_date' => $expiresAt->format('Y-m-d'),
            ],
        );

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'affected' => 2,
            ]);

        self::assertSame($startedAt->toDateString(), $approved->fresh()->accreditation_started_at->toDateString());
        self::assertSame($expiresAt->toDateString(), $approved->fresh()->accreditation_expires_at->toDateString());
        self::assertSame($startedAt->toDateString(), $approvedTwo->fresh()->accreditation_started_at->toDateString());
        self::assertSame($expiresAt->toDateString(), $approvedTwo->fresh()->accreditation_expires_at->toDateString());
        self::assertNotSame($startedAt->toDateString(), $pending->fresh()->accreditation_started_at->toDateString());
        self::assertSame(now()->addDays(10)->toDateString(), $pending->fresh()->accreditation_expires_at->toDateString());
        self::assertNull($pending->fresh()->archived_at);
    }

    public function test_missing_invalid_or_ending_before_starting_period_is_rejected(): void
    {
        Organization::create([
            'name' => 'Approved Org',
            'org_acronym' => 'AO',
            'type' => 'Mother Org',
            'status' => 'Approved',
        ]);

        $this->actingAs($this->user('osas_admin'))
            ->postJson(route('osas.settings.accreditation.update'), [])
            ->assertUnprocessable();

        $this->actingAs($this->user('osas_admin'))
            ->postJson(
                route('osas.settings.accreditation.update'),
                ['accreditation_start_date' => 'not-a-date', 'accreditation_end_date' => 'not-a-date'],
            )
            ->assertUnprocessable();

        $this->actingAs($this->user('osas_admin'))
            ->postJson(
                route('osas.settings.accreditation.update'),
                [
                    'accreditation_start_date' => now()->addYear()->format('Y-m-d'),
                    'accreditation_end_date' => now()->addDay()->format('Y-m-d'),
                ],
            )
            ->assertUnprocessable();

        self::assertNull(Organization::first()->accreditation_started_at);
        self::assertNull(Organization::first()->accreditation_expires_at);
    }

    public function test_non_osas_roles_are_denied_from_setting_the_period(): void
    {
        foreach (['student', 'dean', 'admin'] as $role) {
            $response = $this->actingAs($this->user($role))
                ->postJson(
                    route('osas.settings.accreditation.update'),
                    [
                        'accreditation_start_date' => now()->subDay()->format('Y-m-d'),
                        'accreditation_end_date' => now()->addYear()->format('Y-m-d'),
                    ],
                );

            $response->assertForbidden();
        }
    }

    public function test_current_global_period_helper_returns_the_latest_approved_period_or_null(): void
    {
        self::assertNull(Organization::currentAccreditationPeriod());

        Organization::create([
            'name' => 'Pending Only',
            'org_acronym' => 'P1',
            'type' => 'Mother Org',
            'status' => 'Pending',
            'accreditation_started_at' => now()->subDays(30),
            'accreditation_expires_at' => now()->addDays(30),
        ]);
        self::assertNull(Organization::currentAccreditationPeriod());

        Organization::create([
            'name' => 'Approved Early',
            'org_acronym' => 'E1',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subDays(200),
            'accreditation_expires_at' => now()->addDays(10),
        ]);
        Organization::create([
            'name' => 'Approved Late',
            'org_acronym' => 'L1',
            'type' => 'Sub Org',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subDays(100),
            'accreditation_expires_at' => now()->addDays(200),
        ]);

        $helper = Organization::currentAccreditationPeriod();
        self::assertNotNull($helper);
        self::assertSame(now()->subDays(100)->toDateString(), $helper->started_at->toDateString());
        self::assertSame(now()->addDays(200)->toDateString(), $helper->expires_at->toDateString());
    }

    public function test_accreditation_period_label_formats_school_year_and_dates(): void
    {
        $organization = Organization::create([
            'name' => 'Label Org',
            'org_acronym' => 'LO',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'accreditation_started_at' => Carbon::create(2026, 8, 1),
            'accreditation_expires_at' => Carbon::create(2027, 3, 31),
        ]);

        self::assertSame('SY 2026-2027 · Aug 1, 2026 - Mar 31, 2027', $organization->accreditationPeriodLabel());
        self::assertNull(Organization::create([
            'name' => 'No Period Org',
            'org_acronym' => 'NP',
            'type' => 'Mother Org',
            'status' => 'Approved',
        ])->accreditationPeriodLabel());
    }

    public function test_approving_a_new_application_auto_applies_the_global_period(): void
    {
        Queue::fake();
        Mail::fake();

        $admin = $this->user('osas_admin');

        $globalStartedAt = now()->subDays(30)->startOfDay();
        $globalExpiresAt = now()->addYear()->startOfDay();

        Organization::create([
            'name' => 'Existing Approved',
            'org_acronym' => 'EX',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'accreditation_started_at' => $globalStartedAt,
            'accreditation_expires_at' => $globalExpiresAt,
        ]);

        $requester = (new User)->forceFill([
            'name' => 'Requester One',
            'email' => 'requester-one@example.test',
            'password' => 'not-used',
            'role' => 'admin',
            'student_id' => '12345678',
            'is_active' => true,
        ]);
        $requester->save();

        $application = ApplyOrg::create([
            'organization_name' => 'Brand New Org',
            'organization_acronym' => 'BNO',
            'organization_type' => 'Mother Org',
            'department_id' => null,
            'request_type' => 'apply',
            'application_type' => 'apply',
            'requester_first_name' => 'Requester',
            'requester_last_name' => 'One',
            'requester_email' => $requester->email,
            'requester_position' => 'Organization President',
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('osas.apply-org.approve', $application),
        );

        $response->assertOk()->assertJson(['success' => true]);

        $organization = Organization::where('name', 'Brand New Org')->firstOrFail();
        self::assertSame('Approved', $organization->status);
        self::assertSame($globalStartedAt->toDateString(), $organization->accreditation_started_at->toDateString());
        self::assertSame($globalExpiresAt->toDateString(), $organization->accreditation_expires_at->toDateString());
        self::assertNotNull($organization->accreditationPeriodLabel());
    }

    private function user(string $role): User
    {
        static $id = 0;
        $id++;

        $user = (new User)->forceFill([
            'name' => "Osas User {$id}",
            'email' => "osas-accr-{$id}@example.test",
            'password' => 'not-used',
            'role' => $role,
            'is_active' => true,
        ]);
        $user->save();

        return $user;
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->string('student_id')->nullable();
            $table->foreignId('apply_org_id')->nullable();
            $table->string('temp_position')->nullable();
            $table->timestamps();
        });

        Schema::create('organizations', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('name');
            $table->string('org_acronym')->nullable();
            $table->string('type');
            $table->foreignId('department_id')->nullable();
            $table->string('status')->default('Pending');
            $table->boolean('has_system')->default(false);
            $table->timestamp('accreditation_started_at')->nullable();
            $table->timestamp('accreditation_expires_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->foreignId('organization_id')->nullable();
            $table->string('organization_name');
            $table->string('organization_acronym')->nullable();
            $table->string('organization_type');
            $table->foreignId('department_id')->nullable();
            $table->string('request_type')->default('request');
            $table->string('application_type')->nullable();
            $table->string('requester_first_name')->nullable();
            $table->string('requester_last_name')->nullable();
            $table->string('requester_email');
            $table->string('requester_position');
            $table->string('status')->default('Pending');
            $table->string('accreditation_year')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table): void {
            $table->temporary();
            $table->foreignId('organization_id');
            $table->foreignId('user_id');
            $table->string('role')->nullable();
            $table->string('membership_status')->default('Active');
            $table->timestamps();
        });
    }
}
