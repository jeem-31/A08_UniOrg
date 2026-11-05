<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\RenewController;
use App\Models\ApplyOrg;
use App\Models\Organization;
use App\Models\User;
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

final class RenewDeanAndAdviserExemptionTest extends TestCase
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
        Notification::fake();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('election_officer_transitions');
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

    public function test_renewal_succeeds_when_same_dean_of_same_department_is_reused(): void
    {
        $organization = Organization::create([
            'name' => 'Computer Science Society',
            'org_acronym' => 'CSS',
            'type' => 'Mother Org',
            'department_id' => 1,
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);

        // Existing Dean of department 1
        $dean = User::create([
            'student_id' => '01-1234-5678',
            'first_name' => 'John',
            'last_name' => 'Dean',
            'email' => 'dean.cics@uniorg.edu',
            'role' => 'super_admin',
            'department_id' => 1,
            'is_active' => true,
            'temp_position' => 'Dean',
        ]);

        $officer = User::create([
            'student_id' => '2026-0001',
            'first_name' => 'Alice',
            'last_name' => 'President',
            'email' => 'alice@css.edu',
            'role' => 'admin',
            'department_id' => 1,
            'is_active' => true,
        ]);

        DB::table('organization_user')->insert([
            'organization_id' => $organization->id,
            'user_id' => $officer->id,
            'role' => 'President',
            'membership_status' => 'Active',
            'management_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('election_officer_transitions')->insert([
            'organization_id' => $organization->id,
            'status' => 'completed',
            'winners_snapshot' => json_encode([
                ['student_id' => '2026-0001', 'position' => 'President', 'user_id' => $officer->id],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Auto-generated pending renewal placeholder
        ApplyOrg::create([
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'organization_acronym' => $organization->org_acronym,
            'organization_type' => 'Mother Org',
            'department_id' => 1,
            'request_type' => 'request',
            'application_type' => 'renew',
            'status' => 'Pending',
            'auto_generated' => true,
        ]);

        Auth::login($officer);
        session(['current_organization_id' => $organization->id]);

        $request = $this->buildRenewalRequest([
            'renewer_email' => 'alice@css.edu',
            'renewer_student_id' => '2026-0001',
            'renewer_first_name' => 'Alice',
            'renewer_last_name' => 'President',
            'renewer_position' => 'Organization President',
            'dean' => [
                'id_number' => '01-1234-5678',
                'email' => 'dean.cics@uniorg.edu',
                'first_name' => 'John',
                'last_name' => 'Dean',
            ],
            'officers' => [
                'president' => [
                    'student_id' => '2026-0001',
                    'first_name' => 'Alice',
                    'last_name' => 'President',
                    'email' => 'alice@css.edu',
                ],
            ],
        ]);

        $response = $this->app->make(RenewController::class)->submit($request);
        self::assertSame(202, $response->getStatusCode(), $response->getContent());

        $completed = ApplyOrg::where('organization_id', $organization->id)->first();
        self::assertNotNull($completed);
        self::assertFalse((bool) $completed->auto_generated);
        self::assertSame('For-Checking', $completed->status);
    }

    public function test_renewal_succeeds_when_same_adviser_of_same_org_is_reused(): void
    {
        $organization = Organization::create([
            'name' => 'Information Technology Society',
            'org_acronym' => 'ITS',
            'type' => 'Mother Org',
            'department_id' => 2,
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);

        // Existing Adviser for this organization
        $adviser = User::create([
            'student_id' => '02-2345-6789',
            'first_name' => 'Robert',
            'last_name' => 'Adviser',
            'email' => 'robert.adviser@its.edu',
            'role' => 'admin',
            'department_id' => 2,
            'is_active' => true,
        ]);

        DB::table('organization_user')->insert([
            'organization_id' => $organization->id,
            'user_id' => $adviser->id,
            'role' => 'Senior Adviser',
            'membership_status' => 'Active',
            'management_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Auto-generated pending renewal placeholder
        ApplyOrg::create([
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'organization_acronym' => $organization->org_acronym,
            'organization_type' => 'Mother Org',
            'department_id' => 2,
            'request_type' => 'request',
            'application_type' => 'renew',
            'status' => 'Pending',
            'auto_generated' => true,
        ]);

        Auth::login($adviser);
        session(['current_organization_id' => $organization->id]);

        $request = $this->buildRenewalRequest([
            'renewer_email' => 'robert.adviser@its.edu',
            'renewer_student_id' => null,
            'renewer_first_name' => 'Robert',
            'renewer_last_name' => 'Adviser',
            'renewer_position' => 'Organization Adviser',
            'dean' => [
                'id_number' => '00-0000-0001',
                'email' => 'new.dean@uniorg.edu',
                'first_name' => 'New',
                'last_name' => 'Dean',
            ],
            'officers' => [
                'senior_adviser' => [
                    'id_number' => '02-2345-6789',
                    'first_name' => 'Robert',
                    'last_name' => 'Adviser',
                    'email' => 'robert.adviser@its.edu',
                ],
                'president' => [
                    'student_id' => '2026-9999',
                    'first_name' => 'New',
                    'last_name' => 'President',
                    'email' => 'new.pres@its.edu',
                ],
            ],
        ]);

        $response = $this->app->make(RenewController::class)->submit($request);
        self::assertSame(202, $response->getStatusCode(), $response->getContent());
    }

    public function test_renewal_succeeds_when_same_comelec_of_same_org_is_reused(): void
    {
        $organization = Organization::create([
            'name' => 'COMELEC Test Society',
            'org_acronym' => 'CTS',
            'type' => 'Mother Org',
            'department_id' => 1,
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);

        $comelecUser = User::create([
            'student_id' => '2026-8888',
            'first_name' => 'Carlos',
            'last_name' => 'Comelec',
            'email' => 'carlos.comelec@cts.edu',
            'role' => 'admin',
            'department_id' => 1,
            'is_active' => true,
        ]);

        DB::table('organization_user')->insert([
            'organization_id' => $organization->id,
            'user_id' => $comelecUser->id,
            'role' => 'COMELEC',
            'membership_status' => 'Active',
            'management_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $president = User::create([
            'student_id' => '2026-0001',
            'first_name' => 'Alice',
            'last_name' => 'President',
            'email' => 'alice@cts.edu',
            'role' => 'admin',
            'department_id' => 1,
            'is_active' => true,
        ]);

        DB::table('organization_user')->insert([
            'organization_id' => $organization->id,
            'user_id' => $president->id,
            'role' => 'President',
            'membership_status' => 'Active',
            'management_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('election_officer_transitions')->insert([
            'organization_id' => $organization->id,
            'status' => 'completed',
            'winners_snapshot' => json_encode([
                ['student_id' => '2026-0001', 'position' => 'President', 'user_id' => $president->id],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ApplyOrg::create([
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'organization_acronym' => $organization->org_acronym,
            'organization_type' => 'Mother Org',
            'department_id' => 1,
            'request_type' => 'request',
            'application_type' => 'renew',
            'status' => 'Pending',
            'auto_generated' => true,
        ]);

        Auth::login($president);
        session(['current_organization_id' => $organization->id]);

        $request = $this->buildRenewalRequest([
            'renewer_email' => 'alice@cts.edu',
            'renewer_student_id' => '2026-0001',
            'renewer_first_name' => 'Alice',
            'renewer_last_name' => 'President',
            'renewer_position' => 'Organization President',
            'dean' => [
                'id_number' => '00-0000-0001',
                'email' => 'dean@cts.edu',
                'first_name' => 'Dean',
                'last_name' => 'CTS',
            ],
            'officers' => [
                'president' => [
                    'student_id' => '2026-0001',
                    'first_name' => 'Alice',
                    'last_name' => 'President',
                    'email' => 'alice@cts.edu',
                ],
                'comelec' => [
                    'student_id' => '2026-8888',
                    'first_name' => 'Carlos',
                    'last_name' => 'Comelec',
                    'email' => 'carlos.comelec@cts.edu',
                    'position' => 'COMELEC',
                ],
            ],
        ]);

        $response = $this->app->make(RenewController::class)->submit($request);
        self::assertSame(202, $response->getStatusCode(), $response->getContent());
    }

    public function test_renewal_rejects_when_dean_id_belongs_to_different_department_user(): void
    {
        $organization = Organization::create([
            'name' => 'Engineering Society',
            'org_acronym' => 'ES',
            'type' => 'Mother Org',
            'department_id' => 1,
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);

        // User belongs to Department 2 (different department) and is NOT a super_admin/dean
        User::create([
            'student_id' => '03-3456-7890',
            'first_name' => 'Other',
            'last_name' => 'Person',
            'email' => 'other.person@dept2.edu',
            'role' => 'student',
            'department_id' => 2,
            'is_active' => true,
        ]);

        $officer = User::create([
            'student_id' => '2026-0001',
            'first_name' => 'Alice',
            'last_name' => 'President',
            'email' => 'alice@es.edu',
            'role' => 'admin',
            'department_id' => 1,
            'is_active' => true,
        ]);

        DB::table('organization_user')->insert([
            'organization_id' => $organization->id,
            'user_id' => $officer->id,
            'role' => 'President',
            'membership_status' => 'Active',
            'management_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ApplyOrg::create([
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'organization_acronym' => $organization->org_acronym,
            'organization_type' => 'Mother Org',
            'department_id' => 1,
            'request_type' => 'request',
            'application_type' => 'renew',
            'status' => 'Pending',
            'auto_generated' => true,
        ]);

        Auth::login($officer);
        session(['current_organization_id' => $organization->id]);

        $request = $this->buildRenewalRequest([
            'renewer_email' => 'alice@es.edu',
            'renewer_student_id' => '2026-0001',
            'renewer_first_name' => 'Alice',
            'renewer_last_name' => 'President',
            'renewer_position' => 'Organization President',
            'dean' => [
                'id_number' => '03-3456-7890',
                'email' => 'other.person@dept2.edu',
                'first_name' => 'Other',
                'last_name' => 'Person',
            ],
            'officers' => [
                'president' => [
                    'student_id' => '2026-0001',
                    'first_name' => 'Alice',
                    'last_name' => 'President',
                    'email' => 'alice@es.edu',
                ],
            ],
        ]);

        $response = $this->app->make(RenewController::class)->submit($request);
        self::assertFalse($response->original['success'] ?? true);
        self::assertStringContainsString('already registered', $response->original['message'] ?? '');
    }

    public function test_renewal_rejects_when_adviser_id_belongs_to_unrelated_active_user(): void
    {
        $organization = Organization::create([
            'name' => 'Math Society',
            'org_acronym' => 'MS',
            'type' => 'Mother Org',
            'department_id' => 1,
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);

        // User belongs to an unrelated organization
        User::create([
            'student_id' => '04-4567-8901',
            'first_name' => 'Stranger',
            'last_name' => 'Adviser',
            'email' => 'stranger@other.edu',
            'role' => 'admin',
            'department_id' => 3,
            'is_active' => true,
        ]);

        $officer = User::create([
            'student_id' => '2026-0001',
            'first_name' => 'Bob',
            'last_name' => 'President',
            'email' => 'bob@ms.edu',
            'role' => 'admin',
            'department_id' => 1,
            'is_active' => true,
        ]);

        DB::table('organization_user')->insert([
            'organization_id' => $organization->id,
            'user_id' => $officer->id,
            'role' => 'President',
            'membership_status' => 'Active',
            'management_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ApplyOrg::create([
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'organization_acronym' => $organization->org_acronym,
            'organization_type' => 'Mother Org',
            'department_id' => 1,
            'request_type' => 'request',
            'application_type' => 'renew',
            'status' => 'Pending',
            'auto_generated' => true,
        ]);

        Auth::login($officer);
        session(['current_organization_id' => $organization->id]);

        $request = $this->buildRenewalRequest([
            'renewer_email' => 'bob@ms.edu',
            'renewer_student_id' => '2026-0001',
            'renewer_first_name' => 'Bob',
            'renewer_last_name' => 'President',
            'renewer_position' => 'Organization President',
            'dean' => [
                'id_number' => '00-1111-2222',
                'email' => 'math.dean@uniorg.edu',
                'first_name' => 'Math',
                'last_name' => 'Dean',
            ],
            'officers' => [
                'senior_adviser' => [
                    'id_number' => '04-4567-8901',
                    'first_name' => 'Stranger',
                    'last_name' => 'Adviser',
                    'email' => 'stranger@other.edu',
                ],
                'president' => [
                    'student_id' => '2026-0001',
                    'first_name' => 'Bob',
                    'last_name' => 'President',
                    'email' => 'bob@ms.edu',
                ],
            ],
        ]);

        $response = $this->app->make(RenewController::class)->submit($request);
        self::assertFalse($response->original['success'] ?? true);
        self::assertStringContainsString('already registered', $response->original['message'] ?? '');
    }

    private function buildRenewalRequest(array $overrides = []): Request
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
        ] as $field) {
            $files[$field] = UploadedFile::fake()->createWithContent("{$field}.pdf", "bytes:{$field}");
        }
        $files['list_of_members'] = [
            UploadedFile::fake()->createWithContent('Members.pdf', 'member bytes'),
        ];

        $payload = array_merge([
            'renewer_student_id' => '2026-0001',
            'renewer_first_name' => 'Rene',
            'renewer_last_name' => 'Walsh',
            'renewer_email' => 'rene@example.test',
            'renewer_position' => 'Organization President',
            'officers' => [
                'president' => [
                    'student_id' => '2026-0001',
                    'first_name' => 'Rene',
                    'last_name' => 'Walsh',
                    'email' => 'rene@example.test',
                ],
            ],
        ], $overrides);

        $request = Request::create('/admin/renew', 'POST', $payload, [], $files);
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

        Schema::create('election_officer_transitions', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('election_id')->nullable();
            $table->unsignedBigInteger('organization_id');
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->json('winners_snapshot')->nullable();
            $table->timestamps();
        });
    }
}
