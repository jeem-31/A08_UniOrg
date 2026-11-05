<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureActiveAccount;
use App\Models\Department;
use App\Models\Event;
use App\Models\EventProposal;
use App\Models\Organization;
use App\Models\OrganizationSettings;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class OsasCalendarAndProposalsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('accreditation_period_audits');
        Schema::dropIfExists('apply_orgs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_proposals');
        Schema::dropIfExists('events');
        Schema::dropIfExists('organization_settings');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    private function createSchema(): void
    {
        Schema::dropIfExists('accreditation_period_audits');
        Schema::dropIfExists('apply_orgs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_proposals');
        Schema::dropIfExists('events');
        Schema::dropIfExists('organization_settings');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('users');

        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('user_id');
            $table->string('role')->default('member');
            $table->string('membership_status')->default('Active');
            $table->timestamps();
        });

        Schema::create('apply_orgs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('organization_acronym')->nullable();
            $table->string('status')->default('Approved');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('accreditation_period_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_user_id');
            $table->string('actor_display');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('affected_count');
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->default('Admin');
            $table->string('middle_name')->nullable();
            $table->string('middle_initial')->nullable();
            $table->string('last_name')->default('User');
            $table->string('suffix')->nullable();
            $table->string('name')->default('Admin User');
            $table->string('student_id')->nullable();
            $table->string('email')->unique();
            $table->string('password')->default('secret');
            $table->string('role')->default('osas_admin');
            $table->boolean('is_active')->default(true);
            $table->foreignId('department_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('management_version')->default(1);
            $table->timestamp('password_changed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('department_name');
            $table->string('department_acronym')->nullable();
            $table->timestamps();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('org_acronym')->nullable();
            $table->string('type')->default('Academic');
            $table->string('status')->default('Approved');
            $table->foreignId('department_id')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id');
            $table->string('primary_color')->default('#26488C');
            $table->string('secondary_color')->default('#C4973B');
            $table->string('logo_url')->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('user_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->string('venue')->default('Main Hall');
            $table->decimal('planned_budget', 12, 2)->default(5000.00);
            $table->json('images')->nullable();
            $table->boolean('is_published')->default(true);
            $table->string('status')->default('published');
            $table->string('approval_status')->default('approved');
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dean_approved_at')->nullable();
            $table->string('osas_status')->nullable()->default('approved');
            $table->foreignId('osas_approved_by')->nullable();
            $table->timestamp('osas_approved_at')->nullable();
            $table->timestamp('hardcopy_received_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id');
            $table->foreignId('organization_id')->nullable();
            $table->foreignId('submitted_by')->nullable();
            $table->string('request_letter')->nullable();
            $table->string('status')->default('approved');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('osas_rejection_reason')->nullable();
            $table->foreignId('osas_reviewed_by')->nullable();
            $table->timestamp('osas_reviewed_at')->nullable();
            $table->string('reschedule_status')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->foreignId('reschedule_requested_by')->nullable();
            $table->timestamp('reschedule_requested_at')->nullable();
            $table->foreignId('reschedule_reviewed_by')->nullable();
            $table->timestamp('reschedule_reviewed_at')->nullable();
            $table->text('reschedule_rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id');
            $table->foreignId('user_id');
            $table->timestamps();
        });
    }

    public function test_osas_admin_can_view_calendar_with_proper_event_show_urls(): void
    {
        $admin = User::create([
            'first_name' => 'OSAS',
            'last_name' => 'Admin',
            'name' => 'OSAS Admin',
            'email' => 'osas@lspu.edu.ph',
            'role' => 'osas_admin',
            'is_active' => true,
        ]);

        $dept = Department::create(['department_name' => 'College of Computer Studies', 'department_acronym' => 'CCS']);
        $org = Organization::create([
            'name' => 'Computer Science Society',
            'org_acronym' => 'CSS',
            'type' => 'Academic',
            'status' => 'Approved',
            'department_id' => $dept->id,
        ]);

        OrganizationSettings::create([
            'organization_id' => $org->id,
            'primary_color' => '#26488C',
            'secondary_color' => '#C4973B',
        ]);

        $event = Event::create([
            'organization_id' => $org->id,
            'user_id' => $admin->id,
            'title' => 'Tech Summit 2026',
            'description' => '<p>Annual tech conference</p>',
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(4),
            'venue' => 'Gymnasium',
            'planned_budget' => 15000.00,
            'osas_status' => 'approved',
            'is_published' => true,
        ]);

        $response = $this->withoutMiddleware(EnsureActiveAccount::class)
            ->actingAs($admin)
            ->get(route('osas.calendar'));

        $response->assertStatus(200);
        $response->assertSee('Tech Summit 2026');
        $response->assertViewHas('events', function ($events) use ($event) {
            $matched = collect($events)->firstWhere('id', $event->id);
            return $matched !== null && $matched['url'] === route('osas.proposals.show', $event->id);
        });
    }

    public function test_osas_admin_can_view_proposal_details_page(): void
    {
        $admin = User::create([
            'first_name' => 'OSAS',
            'last_name' => 'Admin',
            'name' => 'OSAS Admin',
            'email' => 'osas@lspu.edu.ph',
            'role' => 'osas_admin',
            'is_active' => true,
        ]);

        $dept = Department::create(['department_name' => 'College of Arts and Sciences', 'department_acronym' => 'CAS']);
        $org = Organization::create([
            'name' => 'LSPU Debate Society',
            'org_acronym' => 'LDS',
            'type' => 'Non-Academic',
            'status' => 'Approved',
            'department_id' => $dept->id,
        ]);

        OrganizationSettings::create([
            'organization_id' => $org->id,
            'primary_color' => '#26488C',
            'secondary_color' => '#C4973B',
        ]);

        $event = Event::create([
            'organization_id' => $org->id,
            'user_id' => $admin->id,
            'title' => 'Inter-Collegiate Debate Cup',
            'description' => '<p>Debate Championship tournament description.</p>',
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(11),
            'venue' => 'Activity Center',
            'planned_budget' => 25000.00,
            'osas_status' => 'pending',
            'is_published' => false,
        ]);

        EventProposal::create([
            'event_id' => $event->id,
            'organization_id' => $org->id,
            'submitted_by' => $admin->id,
            'status' => 'pending',
        ]);

        $response = $this->withoutMiddleware(EnsureActiveAccount::class)
            ->actingAs($admin)
            ->get(route('osas.proposals.show', $event->id));

        $response->assertStatus(200);
        $response->assertSee('Inter-Collegiate Debate Cup');
        $response->assertSee('LSPU Debate Society');
        $response->assertSee('Activity Center');
        $response->assertSee('₱25,000.00');
        $response->assertSee('Approve Proposal');
        $response->assertSee('Reject Proposal');
    }

    public function test_settings_page_activates_account_management_tab_when_page_parameter_is_present(): void
    {
        $admin = User::create([
            'first_name' => 'OSAS',
            'last_name' => 'Admin',
            'name' => 'OSAS Admin',
            'email' => 'osas@lspu.edu.ph',
            'role' => 'osas_admin',
            'is_active' => true,
        ]);

        $response = $this->withoutMiddleware(EnsureActiveAccount::class)
            ->actingAs($admin)
            ->get(route('osas.settings', ['page' => 2]));

        $response->assertStatus(200);
        $response->assertSee('id="tab-manage-accounts"', false);
        $response->assertSee('id="content-manage-accounts" class="settings-content "', false);
        $response->assertSee('id="content-profile" class="settings-content hidden"', false);
    }

    public function test_settings_page_pagination_links_contain_account_management_tab(): void
    {
        $admin = User::create([
            'first_name' => 'OSAS',
            'last_name' => 'Admin',
            'name' => 'OSAS Admin',
            'email' => 'osas@lspu.edu.ph',
            'role' => 'osas_admin',
            'is_active' => true,
        ]);

        // Create enough users to trigger pagination
        for ($i = 1; $i <= 20; $i++) {
            User::create([
                'first_name' => 'Student' . $i,
                'last_name' => 'Test',
                'name' => 'Student' . $i . ' Test',
                'email' => "student{$i}@lspu.edu.ph",
                'role' => 'student',
                'is_active' => true,
            ]);
        }

        $response = $this->withoutMiddleware(EnsureActiveAccount::class)
            ->actingAs($admin)
            ->get(route('osas.settings', ['tab' => 'account-management']));

        $response->assertStatus(200);
        $response->assertSee('tab=manage-accounts');
        $response->assertSee('#manage-accounts');
    }

    public function test_settings_page_renders_dean_setup_and_manage_accounts_tabs(): void
    {
        $admin = User::create([
            'first_name' => 'OSAS',
            'last_name' => 'Admin',
            'name' => 'OSAS Admin',
            'email' => 'osas@lspu.edu.ph',
            'role' => 'osas_admin',
            'is_active' => true,
        ]);

        $manage = $this->withoutMiddleware(EnsureActiveAccount::class)
            ->actingAs($admin)
            ->get(route('osas.settings', ['tab' => 'manage-accounts']));

        $manage->assertStatus(200);
        $manage->assertSee('id="tab-manage-accounts"', false);
        $manage->assertSee('id="content-manage-accounts" class="settings-content "', false);
        $manage->assertSee('id="content-dean-setup" class="settings-content hidden"', false);

        $deans = $this->withoutMiddleware(EnsureActiveAccount::class)
            ->actingAs($admin)
            ->get(route('osas.settings', ['tab' => 'dean-setup']));

        $deans->assertStatus(200);
        $deans->assertSee('id="tab-dean-setup"', false);
        $deans->assertSee('id="content-dean-setup" class="settings-content "', false);
        $deans->assertSee('id="content-manage-accounts" class="settings-content hidden"', false);
    }

    public function test_dean_setup_lists_assigned_deans_by_department(): void
    {
        $admin = User::create([
            'first_name' => 'OSAS',
            'last_name' => 'Admin',
            'name' => 'OSAS Admin',
            'email' => 'osas@lspu.edu.ph',
            'role' => 'osas_admin',
            'is_active' => true,
        ]);

        $ccs = Department::create(['department_name' => 'College of Computer Studies', 'department_acronym' => 'CCS']);
        $nonAcad = Department::create(['department_name' => 'Non-Academic', 'department_acronym' => 'N/A']);

        User::create([
            'first_name' => 'Mia',
            'last_name' => 'Villarica',
            'name' => 'Mia Villarica',
            'email' => 'dean.ccs@lspu.edu.ph',
            'role' => 'super_admin',
            'is_active' => true,
            'department_id' => $ccs->id,
        ]);
        User::create([
            'first_name' => 'Angela',
            'last_name' => 'Cruz',
            'name' => 'Angela Cruz',
            'email' => 'dean.nonacad@lspu.edu.ph',
            'role' => 'super_admin',
            'is_active' => true,
            'department_id' => $nonAcad->id,
        ]);

        // Dean data must be present even without ?tab= in the URL, because the
        // Settings inner tabs switch client-side without a new request.
        foreach ([[], ['tab' => 'dean-setup']] as $params) {
            $response = $this->withoutMiddleware(EnsureActiveAccount::class)
                ->actingAs($admin)
                ->get(route('osas.settings', $params));

            $response->assertStatus(200);
            $response->assertSee('Mia Villarica');
            $response->assertSee('Angela Cruz');
            $response->assertSee('College of Computer Studies');
        }
    }
}
