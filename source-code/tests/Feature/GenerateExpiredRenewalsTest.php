<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\RenewController;
use App\Models\ApplyOrg;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\RenewalRequiredNotification;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

/** **Validates: OSAS-generated renewals move expired approved orgs into the Renewal filter as Pending** */
final class GenerateExpiredRenewalsTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.default' => 'public']);
        Storage::fake('local');
        Storage::fake('public');
        Queue::fake();
        Mail::fake();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('document_library_entries');
        Schema::dropIfExists('processed_documents');
        Schema::dropIfExists('apply_orgs');
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organization_settings');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_generates_pending_renewal_for_expired_approved_orgs_only(): void
    {
        Notification::fake();

        $expired = Organization::create([
            'name' => 'Expired Org',
            'org_acronym' => 'EO',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);
        $dean = $this->attachMember($expired, 'Dean');
        $adviser = $this->attachMember($expired, 'Adviser');
        $president = $this->attachMember($expired, 'President');
        $active = Organization::create([
            'name' => 'Active Org',
            'org_acronym' => 'AO',
            'type' => 'Sub Org',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subMonths(2),
            'accreditation_expires_at' => now()->addMonths(6),
        ]);
        $noPeriod = Organization::create([
            'name' => 'No Period Org',
            'org_acronym' => 'NP',
            'type' => 'Mother Org',
            'status' => 'Approved',
        ]);

        $this->actingAs($this->user('osas_admin'))
            ->postJson(route('osas.apply-org.generate-renewals'))
            ->assertOk()
            ->assertJson(['success' => true, 'created' => 1, 'skipped' => 0]);

        $renewal = ApplyOrg::where('organization_id', $expired->id)->sole();
        self::assertSame('request', $renewal->request_type);
        self::assertSame('renew', $renewal->application_type);
        self::assertSame('Pending', $renewal->status);
        self::assertTrue($renewal->auto_generated);
        self::assertSame('Expired Org', $renewal->organization_name);

        self::assertSame(0, ApplyOrg::where('organization_id', $active->id)->count());
        self::assertSame(0, ApplyOrg::where('organization_id', $noPeriod->id)->count());

        Notification::assertSentTo($dean, RenewalRequiredNotification::class);
        Notification::assertNotSentTo($adviser, RenewalRequiredNotification::class);
        Notification::assertNotSentTo($president, RenewalRequiredNotification::class);
    }

    public function test_skips_orgs_that_already_have_a_pending_renewal(): void
    {
        $expired = Organization::create([
            'name' => 'Expired Org',
            'org_acronym' => 'EO',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);
        $submitted = ApplyOrg::create([
            'organization_id' => $expired->id,
            'organization_name' => 'Expired Org',
            'organization_acronym' => 'EO',
            'organization_type' => 'Mother Org',
            'request_type' => 'request',
            'application_type' => 'renew',
            'auto_generated' => false,
            'requester_first_name' => 'Rene',
            'requester_last_name' => 'Walsh',
            'requester_email' => 'rene@example.test',
            'requester_position' => 'President',
            'status' => 'Pending',
        ]);

        $this->actingAs($this->user('osas_admin'))
            ->postJson(route('osas.apply-org.generate-renewals'))
            ->assertOk()
            ->assertJson(['success' => true, 'created' => 0, 'skipped' => 1]);

        self::assertSame(1, ApplyOrg::count());
        self::assertSame($submitted->id, ApplyOrg::sole()->id);
    }

    public function test_generated_renewal_shows_in_renewal_filter_with_pending_badge(): void
    {
        $expired = Organization::create([
            'name' => 'Expired Org',
            'org_acronym' => 'EO',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);

        $this->actingAs($this->user('osas_admin'))
            ->postJson(route('osas.apply-org.generate-renewals'))
            ->assertOk();

        $response = $this->actingAs($this->user('osas_admin'))
            ->get(route('osas.apply-org.index', ['type' => 'request']));

        $response->assertOk();
        $response->assertSee('Expired Org');
        self::assertSame(1, ApplyOrg::where('request_type', 'request')->where('status', 'Pending')->count());
        self::assertSame($expired->id, ApplyOrg::sole()->organization_id);
    }

    public function test_approve_is_blocked_until_auto_placeholder_is_completed(): void
    {
        $expired = Organization::create([
            'name' => 'Expired Org',
            'org_acronym' => 'EO',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);
        $osas = $this->user('osas_admin');

        $this->actingAs($osas)
            ->postJson(route('osas.apply-org.generate-renewals'))
            ->assertOk();

        $placeholder = ApplyOrg::sole();

        $this->actingAs($osas)
            ->postJson(route('osas.apply-org.approve', $placeholder))
            ->assertStatus(422);

        self::assertSame('Pending', $placeholder->fresh()->status);

        $placeholder->update([
            'auto_generated' => false,
            'requester_first_name' => 'Rene',
            'requester_last_name' => 'Walsh',
            'requester_email' => 'rene@example.test',
            'requester_position' => 'President',
        ]);

        $this->actingAs($osas)
            ->postJson(route('osas.apply-org.approve', $placeholder->fresh()), ['admin_notes' => 'Renewed'])
            ->assertOk()
            ->assertJson(['success' => true]);

        self::assertSame('Approved', $placeholder->fresh()->status);
    }

    public function test_org_submission_completes_the_auto_placeholder_in_place(): void
    {
        $organization = Organization::create([
            'name' => 'Renewing Society',
            'org_acronym' => 'RS',
            'type' => 'Academic',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);
        $officer = $this->user('admin');
        DB::table('organization_user')->insert([
            'organization_id' => $organization->id,
            'user_id' => $officer->id,
            'role' => 'President',
            'membership_status' => 'Active',
            'management_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user('osas_admin'))
            ->postJson(route('osas.apply-org.generate-renewals'))
            ->assertOk();

        $placeholder = ApplyOrg::sole();
        self::assertTrue($placeholder->auto_generated);

        Auth::login($officer);
        session(['current_organization_id' => $organization->id]);
        $response = $this->app->make(RenewController::class)->submit(
            $this->buildRenewalRequest(),
        );
        self::assertSame(202, $response->getStatusCode(), $response->getContent());

        self::assertSame(1, ApplyOrg::where('organization_id', $organization->id)->count());
        $completed = ApplyOrg::sole();
        self::assertSame($placeholder->id, $completed->id);
        self::assertFalse($completed->auto_generated);
        self::assertSame('For-Checking', $completed->status);
        self::assertSame('rene@example.test', $completed->requester_email);
        self::assertIsString($completed->application_form);
        Storage::disk('local')->assertExists($completed->application_form);

        Auth::login($officer);
        session(['current_organization_id' => $organization->id]);
        $second = $this->app->make(RenewController::class)->submit(
            $this->buildRenewalRequest(),
        );
        self::assertSame(409, $second->getStatusCode(), $second->getContent());
        self::assertSame(1, ApplyOrg::where('organization_id', $organization->id)->count());
    }

    public function test_scheduled_command_generates_renewals_and_notifies_dean_only(): void
    {
        Notification::fake();

        $expired = Organization::create([
            'name' => 'Expired Org',
            'org_acronym' => 'EO',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);
        $dean = $this->attachMember($expired, 'Dean');
        $adviser = $this->attachMember($expired, 'Adviser');

        $this->artisan('renewals:generate-expired')->assertSuccessful();

        self::assertSame(1, ApplyOrg::where('organization_id', $expired->id)->where('request_type', 'request')->count());
        Notification::assertSentTo($dean, RenewalRequiredNotification::class);
        Notification::assertNotSentTo($adviser, RenewalRequiredNotification::class);
    }

    private function attachMember(Organization $organization, string $role): User
    {
        $user = $this->user('admin');

        DB::table('organization_user')->insert([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'membership_status' => 'Active',
            'management_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function user(string $role): User
    {
        static $id = 0;
        $id++;

        $user = (new User)->forceFill([
            'name' => "Gen Renewal User {$id}",
            'student_id' => "2026-{$id}",
            'first_name' => 'Test',
            'last_name' => "User {$id}",
            'email' => "gen-renewal-{$id}@example.test",
            'password' => 'not-used',
            'role' => $role,
            'is_active' => true,
        ]);
        $user->save();

        return $user;
    }

    private function buildRenewalRequest(): Request
    {
        $files = [];
        foreach ([
            'adviser_commitment',
            'application_form',
            'certification_of_officers',
            'plan_of_activities',
            'constitution_bylaws',
            'certification_dean',
            'financial_report',
            'accomplishment_reports',
            'renewal_form',
            'letter_of_application',
        ] as $field) {
            $files[$field] = UploadedFile::fake()->createWithContent("{$field}.pdf", "bytes:{$field}");
        }
        $files['list_of_members'] = [
            UploadedFile::fake()->createWithContent('Members 1.pdf', 'member bytes 1'),
        ];

        $request = Request::create('/admin/renew', 'POST', [
            'renewer_id_number' => '2026-0001',
            'renewer_first_name' => 'Rene',
            'renewer_last_name' => 'Walsh',
            'renewer_email' => 'rene@example.test',
            'renewer_position' => 'President',
            'officers' => [[
                'student_id' => '2026-0002',
                'first_name' => 'Olive',
                'last_name' => 'Officer',
                'position' => 'Vice President',
                'email' => 'officer@example.test',
            ]],
        ], [], $files);
        $request->headers->set('Accept', 'application/json');

        return $request;
    }

    private function createSchema(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('name');
            $table->string('org_acronym')->nullable();
            $table->string('type');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('status')->nullable();
            $table->boolean('has_system')->default(false);
            $table->timestamp('accreditation_started_at')->nullable();
            $table->timestamp('accreditation_expires_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_settings', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('logo_url')->nullable();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('name')->nullable();
            $table->string('student_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('middle_initial')->nullable();
            $table->string('last_name')->nullable();
            $table->string('suffix')->nullable();
            $table->string('email')->unique();
            $table->string('course')->nullable();
            $table->string('year_level')->nullable();
            $table->string('section')->nullable();
            $table->string('role')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('apply_org_id')->nullable();
            $table->string('temp_position')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->nullable();
            $table->string('membership_status')->nullable();
            $table->unsignedInteger('management_version')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->temporary();
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('organization_name');
            $table->string('organization_acronym')->nullable();
            $table->string('organization_type');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('request_type')->nullable();
            $table->string('application_type')->nullable();
            $table->boolean('auto_generated')->default(false);
            $table->string('requester_first_name')->nullable();
            $table->string('requester_last_name')->nullable();
            $table->string('requester_email')->nullable();
            $table->string('requester_position')->nullable();
            $table->string('accreditation_form')->nullable();
            $table->string('letter_of_application')->nullable();
            $table->string('constitution_bylaws')->nullable();
            $table->string('adviser_commitment')->nullable();
            $table->string('application_form')->nullable();
            $table->string('certification_of_officers')->nullable();
            $table->string('plan_of_activities')->nullable();
            $table->string('certification_dean')->nullable();
            $table->string('financial_report')->nullable();
            $table->string('accomplishment_reports')->nullable();
            $table->string('renewal_form')->nullable();
            $table->text('other_files')->nullable();
            $table->text('list_of_members')->nullable();
            $table->unsignedInteger('current_step')->nullable();
            $table->string('status')->nullable();
            $table->string('accreditation_year')->nullable();
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('processed_documents', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('apply_org_id');
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('storage_disk')->nullable();
            $table->string('source_version')->nullable();
            $table->string('source_fingerprint', 64)->nullable();
            $table->timestamp('association_ready_at')->nullable();
            $table->string('active_cycle_id')->nullable();
            $table->string('cycle_kind')->nullable();
            $table->unsignedTinyInteger('cycle_attempt_count')->default(0);
            $table->timestamp('processing_started_at')->nullable();
            $table->unsignedBigInteger('processing_duration_ms')->nullable();
            $table->string('output_source_version')->nullable();
            $table->string('failure_code')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_type_name')->nullable();
            $table->text('summary')->nullable();
            $table->json('tags')->nullable();
            $table->json('key_information')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('processing_status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('summary_version')->default(0);
            $table->timestamp('summary_processed_at')->nullable();
            $table->string('resummarization_status')->default('idle');
            $table->string('resummarization_cycle_id')->nullable();
            $table->text('resummarization_error_message')->nullable();
            $table->boolean('last_resummarization_changed')->nullable();
            $table->timestamps();
            $table->unique(['apply_org_id', 'document_field', 'document_slot']);
        });

        Schema::create('document_library_entries', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('apply_org_id');
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('requirement_label');
            $table->string('original_filename');
            $table->string('storage_disk');
            $table->string('file_path');
            $table->string('source_version')->nullable();
            $table->string('source_fingerprint', 64)->nullable();
            $table->timestamps();
            $table->unique(['apply_org_id', 'document_field', 'document_slot']);
        });
    }
}
