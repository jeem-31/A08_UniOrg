<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\RenewController;
use App\Http\Controllers\ApplyOrgController;
use App\Mail\ApplyOrgApproved;
use App\Mail\ApplyOrgRejected;
use App\Mail\ApplyOrgSubmitted;
use App\Models\ApplyOrg;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewApplyOrgNotification;
use App\Notifications\RenewalRequiredNotification;
use App\Services\ExpiredRenewalGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class RenewalEmailNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.default' => 'public']);
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        Notification::fake();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('processed_documents');
        Schema::dropIfExists('document_library_entries');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('apply_orgs');
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    /** TEST 1: Dean gets notified when org expires */
    public function test_expired_org_sends_renewal_notification_to_dean_only(): void
    {
        $org = Organization::create([
            'name' => 'Expired Org',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);

        $dean = $this->attachMember($org, 'Dean');
        $adviser = $this->attachMember($org, 'Adviser');
        $president = $this->attachMember($org, 'President');

        $generator = app(ExpiredRenewalGenerator::class);
        $generator->generate();

        Notification::assertSentTo($dean, RenewalRequiredNotification::class);
        Notification::assertNotSentTo($adviser, RenewalRequiredNotification::class);
        Notification::assertNotSentTo($president, RenewalRequiredNotification::class);

        $placeholder = ApplyOrg::where('organization_id', $org->id)->sole();
        self::assertSame('request', $placeholder->request_type);
        self::assertSame('renew', $placeholder->application_type);
        self::assertTrue($placeholder->auto_generated);
    }

    /** TEST 2: Requester gets confirmation email after renewal submit */
    public function test_renewal_submission_sends_confirmation_email_to_requester(): void
    {
        $org = Organization::create([
            'name' => 'Renewing Society',
            'type' => 'Academic',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);

        $officer = $this->createAdminUser();
        $this->attachMember($org, 'President', $officer);

        // Generate placeholder
        app(ExpiredRenewalGenerator::class)->generate();

        Auth::login($officer);
        session(['current_organization_id' => $org->id]);

        $response = $this->app->make(RenewController::class)->submit(
            $this->buildRenewalRequest('requester@test.com'),
        );

        self::assertSame(202, $response->getStatusCode(), $response->getContent());

        // Verify the confirmation email was queued via Mail fake
        Mail::assertSent(ApplyOrgSubmitted::class);
    }

    /** TEST 3: OSAS admins get notified on renewal submission */
    public function test_renewal_submission_notifies_all_osas_admins(): void
    {
        $org = Organization::create([
            'name' => 'Renewing Society',
            'type' => 'Academic',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);

        $osas1 = $this->createOsasAdmin('osas1@test.com');
        $osas2 = $this->createOsasAdmin('osas2@test.com');
        $regularUser = $this->createAdminUser();

        $officer = $this->createAdminUser();
        $this->attachMember($org, 'President', $officer);

        app(ExpiredRenewalGenerator::class)->generate();

        Auth::login($officer);
        session(['current_organization_id' => $org->id]);

        $response = $this->app->make(RenewController::class)->submit(
            $this->buildRenewalRequest(),
        );

        self::assertSame(202, $response->getStatusCode(), $response->getContent());

        Notification::assertSentTo($osas1, NewApplyOrgNotification::class);
        Notification::assertSentTo($osas2, NewApplyOrgNotification::class);
        Notification::assertNotSentTo($regularUser, NewApplyOrgNotification::class);
    }

    /** TEST 4: OSAS approve sends approved email */
    public function test_osas_approve_sends_approved_email_to_requester(): void
    {
        $org = Organization::create([
            'name' => 'Renewing Society',
            'type' => 'Academic',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);

        $osas = $this->createOsasAdmin();

        // Controller looks up User by email — must exist
        $requesterUser = $this->createAdminUser('rene@test.com');

        $applyOrg = ApplyOrg::create([
            'organization_id' => $org->id,
            'organization_name' => $org->name,
            'organization_acronym' => $org->org_acronym ?? 'RS',
            'organization_type' => $org->type,
            'request_type' => 'request',
            'application_type' => 'renew',
            'auto_generated' => false,
            'requester_first_name' => 'Rene',
            'requester_last_name' => 'Walsh',
            'requester_email' => 'rene@test.com',
            'requester_position' => 'President',
            'status' => 'Pending',
            'current_step' => 5,
        ]);

        $this->actingAs($osas);
        $response = $this->postJson(
            route('osas.apply-org.approve', $applyOrg->id),
            ['admin_notes' => 'Approved for renewal'],
        );

        // Verify approve succeeded in DB
        self::assertSame('Approved', $applyOrg->fresh()->status);

        // Verify mail was sent (check via Mail::sent() directly)
        $sentMailables = \Illuminate\Support\Facades\Mail::sent(ApplyOrgApproved::class);
        self::assertNotEmpty($sentMailables, 'ApplyOrgApproved mailable should have been sent');

        Mail::assertSent(ApplyOrgApproved::class, function (ApplyOrgApproved $mail) {
            return $mail->hasTo('rene@test.com');
        });
    }

    /** TEST 5: OSAS reject sends rejected email with reason */
    public function test_osas_reject_sends_rejected_email_with_reason(): void
    {
        $org = Organization::create([
            'name' => 'Renewing Society',
            'type' => 'Academic',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);

        $osas = $this->createOsasAdmin();

        $applyOrg = ApplyOrg::create([
            'organization_id' => $org->id,
            'organization_name' => $org->name,
            'organization_acronym' => $org->org_acronym ?? 'RS',
            'organization_type' => $org->type,
            'request_type' => 'request',
            'application_type' => 'renew',
            'auto_generated' => false,
            'requester_first_name' => 'Rene',
            'requester_last_name' => 'Walsh',
            'requester_email' => 'rene@test.com',
            'requester_position' => 'President',
            'status' => 'Pending',
            'current_step' => 5,
        ]);

        $reason = 'Missing financial report document';

        $this->actingAs($osas);

        $request = \Illuminate\Http\Request::create(
            "/osas/apply-org/{$applyOrg->id}/reject",
            'POST',
            ['reason' => $reason],
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
        );

        $response = $this->app->make(ApplyOrgController::class)->reject($request, $applyOrg);

        self::assertSame(200, $response->getStatusCode(), $response->getContent());

        self::assertSame('Rejected', $applyOrg->fresh()->status);

        Mail::assertSent(ApplyOrgRejected::class);
    }

    /** TEST 6: Cannot approve auto-generated placeholder */
    public function test_cannot_approve_auto_generated_placeholder(): void
    {
        $org = Organization::create([
            'name' => 'Expired Org',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);

        $osas = $this->createOsasAdmin();

        $placeholder = ApplyOrg::create([
            'organization_id' => $org->id,
            'organization_name' => $org->name,
            'organization_type' => $org->type,
            'request_type' => 'request',
            'application_type' => 'renew',
            'auto_generated' => true,
            'status' => 'Pending',
            'current_step' => 1,
            'admin_notes' => 'Auto-generated: accreditation expired.',
        ]);

        $request = \Illuminate\Http\Request::create(
            "/osas/apply-org/{$placeholder->id}/approve",
            'POST',
        );
        $request->headers->set('Accept', 'application/json');

        $this->actingAs($osas);
        $response = $this->app->make(ApplyOrgController::class)->approve($request, $placeholder);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('Pending', $placeholder->fresh()->status);

        Mail::assertNothingSent();
    }

    /** TEST 7: Submission completes placeholder and sends email */
    public function test_submission_completes_placeholder_and_sends_email(): void
    {
        $org = Organization::create([
            'name' => 'Renewing Society',
            'type' => 'Academic',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);

        $officer = $this->createAdminUser();
        $this->attachMember($org, 'President', $officer);

        // Generate placeholder
        app(ExpiredRenewalGenerator::class)->generate();
        $placeholder = ApplyOrg::sole();
        self::assertTrue($placeholder->auto_generated);

        // Submit renewal — fills the placeholder in-place
        Auth::login($officer);
        session(['current_organization_id' => $org->id]);

        $response = $this->app->make(RenewController::class)->submit(
            $this->buildRenewalRequest('submitter@test.com'),
        );

        self::assertSame(202, $response->getStatusCode(), $response->getContent());

        // Same record, not a new one
        $completed = ApplyOrg::sole();
        self::assertSame($placeholder->id, $completed->id);
        self::assertFalse($completed->auto_generated);
        self::assertSame('For-Checking', $completed->status);
        self::assertSame('submitter@test.com', $completed->requester_email);

        // Confirmation email sent
        Mail::assertSent(ApplyOrgSubmitted::class, function (ApplyOrgSubmitted $mail) {
            return $mail->hasTo('submitter@test.com');
        });
    }

    /** TEST 8: Duplicate submission returns 409 */
    public function test_duplicate_submission_returns_409(): void
    {
        $org = Organization::create([
            'name' => 'Renewing Society',
            'type' => 'Academic',
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);

        $officer = $this->createAdminUser();
        $this->attachMember($org, 'President', $officer);

        app(ExpiredRenewalGenerator::class)->generate();

        // First submission
        Auth::login($officer);
        session(['current_organization_id' => $org->id]);

        $response1 = $this->app->make(RenewController::class)->submit(
            $this->buildRenewalRequest(),
        );
        self::assertSame(202, $response1->getStatusCode(), $response1->getContent());

        // Second submission — blocked
        Auth::login($officer);
        session(['current_organization_id' => $org->id]);

        $response2 = $this->app->make(RenewController::class)->submit(
            $this->buildRenewalRequest(),
        );
        self::assertSame(409, $response2->getStatusCode(), $response2->getContent());

        self::assertSame(1, ApplyOrg::where('organization_id', $org->id)->count());
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function createAdminUser(?string $email = null): User
    {
        static $id = 0;
        $id++;

        $user = (new User)->forceFill([
            'first_name' => 'Test',
            'last_name' => "Admin {$id}",
            'email' => $email ?? "admin-{$id}@test.com",
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
            'student_id' => "ADMIN-{$id}",
        ]);
        $user->save();

        return $user;
    }

    private function createOsasAdmin(?string $email = null): User
    {
        static $id = 0;
        $id++;

        $user = (new User)->forceFill([
            'first_name' => 'OSAS',
            'last_name' => "Admin {$id}",
            'email' => $email ?? "osas-{$id}@test.com",
            'password' => bcrypt('password'),
            'role' => 'osas_admin',
            'is_active' => true,
            'student_id' => "OSAS-{$id}",
        ]);
        $user->save();

        return $user;
    }

    private function attachMember(
        Organization $organization,
        string $role,
        ?User $user = null,
    ): User {
        $user ??= (new User)->forceFill([
            'first_name' => 'Member',
            'last_name' => 'Test',
            'email' => uniqid('member-', true).'@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
            'student_id' => uniqid('MEM-'),
        ]);
        if (! $user->exists) {
            $user->save();
        }

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

    private function buildRenewalRequest(?string $email = null): \Illuminate\Http\Request
    {
        $email ??= 'rene@test.com';
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

        $request = \Illuminate\Http\Request::create('/admin/renew', 'POST', [
            'renewer_id_number' => '2026-0001',
            'renewer_first_name' => 'Rene',
            'renewer_last_name' => 'Walsh',
            'renewer_email' => $email,
            'renewer_position' => 'President',
            'officers' => [[
                'student_id' => '2026-0002',
                'first_name' => 'Olive',
                'last_name' => 'Officer',
                'position' => 'Vice President',
                'email' => 'officer@test.com',
            ]],
        ], [], $files);
        $request->headers->set('Accept', 'application/json');

        return $request;
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('middle_initial')->nullable();
            $table->string('last_name')->nullable();
            $table->string('suffix')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->string('student_id')->nullable();
            $table->string('course')->nullable();
            $table->string('year_level')->nullable();
            $table->string('section')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('apply_org_id')->nullable();
            $table->string('temp_position')->nullable();
            $table->timestamps();
        });

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
