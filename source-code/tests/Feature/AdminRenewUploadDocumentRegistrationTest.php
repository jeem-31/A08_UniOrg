<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\RenewController;
use App\Jobs\ProcessDocumentAnalysis;
use App\Models\ApplyOrg;
use App\Models\DocumentLibraryEntry;
use App\Models\Organization;
use App\Models\ProcessedDocument;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** **Validates: Requirements 1.1, 1.2, 1.4, 7.1, 8.1, 8.4, 8.5, 9.3, 9.4** */
final class AdminRenewUploadDocumentRegistrationTest extends TestCase
{
    private User $administrator;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.default' => 'public']);
        Storage::fake('public');
        Queue::fake();
        Mail::fake();
        $this->createSchema();

        $this->organization = Organization::query()->create([
            'name' => 'Renewing Society',
            'org_acronym' => 'RS',
            'type' => 'Academic',
            'department_id' => null,
            'status' => 'Approved',
            'accreditation_started_at' => now()->subYears(2),
            'accreditation_expires_at' => now()->subDays(10),
        ]);
        $this->administrator = User::query()->create([
            'student_id' => 'ADMIN-1',
            'first_name' => 'Ada',
            'last_name' => 'Admin',
            'email' => 'admin@example.test',
            'role' => 'admin',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        DB::table('organization_user')->insert([
            'organization_id' => $this->organization->id,
            'user_id' => $this->administrator->id,
            'role' => 'President',
            'membership_status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Auth::login($this->administrator);
        session(['current_organization_id' => $this->organization->id]);
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS pg_temp.document_processing_attempts, pg_temp.document_library_entries, pg_temp.processed_documents, pg_temp.organization_user, pg_temp.apply_orgs, pg_temp.organizations, pg_temp.users CASCADE');
        } else {
            Schema::dropIfExists('document_processing_attempts');
            Schema::dropIfExists('document_library_entries');
            Schema::dropIfExists('processed_documents');
            Schema::dropIfExists('organization_user');
            Schema::dropIfExists('apply_orgs');
            Schema::dropIfExists('organizations');
            Schema::dropIfExists('users');
        }

        parent::tearDown();
    }

    public function test_admin_renewal_registers_catalogued_sources_with_stable_slots_after_commit(): void
    {
        $response = $this->submit($this->documentFiles(2));

        self::assertSame(200, $response->getStatusCode(), $response->getContent());
        $application = ApplyOrg::query()->sole();
        self::assertSame('Pending', $application->status);
        self::assertIsString($application->application_form);
        self::assertFalse(str_starts_with($application->application_form, '{'));
        Storage::disk('public')->assertExists($application->application_form);
        self::assertSame(
            'bytes:application_form',
            Storage::disk('public')->get($application->application_form),
        );

        $memberMetadata = json_decode((string) $application->list_of_members, true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(2, $memberMetadata);
        self::assertNotSame($memberMetadata[0]['slot'], $memberMetadata[1]['slot']);
        foreach ($memberMetadata as $index => $metadata) {
            self::assertStringStartsWith('member-', $metadata['slot']);
            self::assertSame('public', $metadata['storage_disk']);
            self::assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $metadata['source_version']);
            self::assertSame(hash('sha256', 'member bytes '.($index + 1)), $metadata['source_fingerprint']);
        }

        self::assertCount(11, ProcessedDocument::query()->get());
        self::assertCount(11, DocumentLibraryEntry::query()->get());
        $applicationFormAnalysis = ProcessedDocument::query()->where('document_field', 'application_form')->sole();
        $applicationFormEntry = DocumentLibraryEntry::query()->where('document_field', 'application_form')->sole();
        self::assertSame($applicationFormAnalysis->source_version, $applicationFormEntry->source_version);
        self::assertSame($applicationFormAnalysis->source_fingerprint, $applicationFormEntry->source_fingerprint);
        self::assertSame(
            [
                'accomplishment_reports',
                'adviser_commitment',
                'application_form',
                'certification_dean',
                'certification_of_officers',
                'constitution_bylaws',
                'financial_report',
                'letter_of_application',
                'list_of_members',
                'plan_of_activities',
            ],
            ProcessedDocument::query()->orderBy('document_field')->pluck('document_field')->unique()->values()->all(),
        );
        self::assertSame(
            'application_form.pdf',
            ProcessedDocument::query()->where('document_field', 'application_form')->value('original_filename'),
        );
        self::assertSame(
            hash('sha256', 'bytes:application_form'),
            ProcessedDocument::query()->where('document_field', 'application_form')->value('source_fingerprint'),
        );
        self::assertTrue(ProcessedDocument::query()->whereNull('association_ready_at')->doesntExist());
        self::assertTrue(ProcessedDocument::query()->whereNot('processing_status', 'pending')->doesntExist());
        self::assertTrue(ProcessedDocument::query()->whereNotNull('output_source_version')->doesntExist());

        Queue::assertPushed(ProcessDocumentAnalysis::class, 11);
        Queue::assertPushed(ProcessDocumentAnalysis::class, fn (ProcessDocumentAnalysis $job): bool => $job->connection === 'database'
            && $job->queue === 'document-ai'
            && $job->afterCommit === true);
    }

    public function test_registration_failure_rolls_back_and_cleans_only_new_admin_renewal_uploads(): void
    {
        Storage::disk('public')->put('existing/keep.pdf', 'existing bytes');
        Schema::drop('processed_documents');

        $response = $this->submit($this->documentFiles());

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(0, ApplyOrg::query()->count());
        self::assertSame(0, DocumentLibraryEntry::query()->count());
        self::assertSame(1, User::query()->count());
        Storage::disk('public')->assertExists('existing/keep.pdf');
        self::assertSame(['existing/keep.pdf'], Storage::disk('public')->allFiles());
        Queue::assertNothingPushed();
    }

    /** @return array<string, UploadedFile|list<UploadedFile>> */
    private function documentFiles(int $memberCount = 1): array
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
            'letter_of_application',
        ] as $field) {
            $files[$field] = UploadedFile::fake()->createWithContent("{$field}.pdf", "bytes:{$field}");
        }

        $files['list_of_members'] = [];
        for ($index = 1; $index <= $memberCount; $index++) {
            $files['list_of_members'][] = UploadedFile::fake()->createWithContent(
                "Members {$index}.pdf",
                "member bytes {$index}",
            );
        }

        return $files;
    }

    /** @param array<string, UploadedFile|list<UploadedFile>> $files */
    private function submit(array $files)
    {
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

        return $this->app->make(RenewController::class)->submit($request);
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
            $table->timestamp('accreditation_started_at')->nullable();
            $table->timestamp('accreditation_expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('student_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('middle_initial')->nullable();
            $table->string('last_name')->nullable();
            $table->string('suffix')->nullable();
            $table->string('email')->nullable();
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
            $table->unsignedBigInteger('management_version')->nullable();
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
            $table->string('requester_first_name');
            $table->string('requester_last_name');
            $table->string('requester_email');
            $table->string('requester_position');
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
            $table->text('list_of_members')->nullable();
            $table->text('other_files')->nullable();
            $table->string('renewal_form')->nullable();
            $table->unsignedInteger('current_step')->nullable();
            $table->string('status')->nullable();
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
