<?php

namespace Tests\Feature;

use App\Http\Controllers\ApplyOrgController;
use App\Jobs\ProcessDocumentAnalysis;
use App\Models\ApplyOrg;
use App\Models\DocumentLibraryEntry;
use App\Models\ProcessedDocument;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** **Validates: Requirements 1.1–1.4, 7.1, 8.1, 8.4, 8.5, 9.3, 9.4** */
final class ApplicantUploadDocumentRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.default' => 'public']);
        Storage::fake('public');
        Queue::fake();
        Mail::fake();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS pg_temp.document_processing_attempts, pg_temp.document_library_entries, pg_temp.processed_documents, pg_temp.users, pg_temp.apply_orgs, pg_temp.departments CASCADE');
        } else {
            Schema::dropIfExists('document_processing_attempts');
            Schema::dropIfExists('document_library_entries');
            Schema::dropIfExists('processed_documents');
            Schema::dropIfExists('users');
            Schema::dropIfExists('apply_orgs');
            Schema::dropIfExists('departments');
        }

        parent::tearDown();
    }

    public function test_application_uploads_persist_provenance_and_dispatch_after_commit(): void
    {
        $documentFields = [
            'adviser_commitment',
            'application_form',
            'certification_of_officers',
            'plan_of_activities',
            'constitution_bylaws',
            'certification_dean',
            'letter_of_application',
        ];
        $files = [];
        foreach ($documentFields as $field) {
            $files[$field] = UploadedFile::fake()->createWithContent("{$field}.pdf", "bytes:{$field}");
        }
        $files['list_of_members'] = [
            UploadedFile::fake()->createWithContent('Original Members.pdf', 'member bytes'),
        ];

        $response = $this->submit([
            'request_type' => 'apply',
            'application_type' => 'apply',
            'organization_name' => 'Document Society',
            'organization_acronym' => 'DOCS',
            'organization_type' => 'Mother Org',
            'requester_first_name' => 'Ari',
            'requester_last_name' => 'Reyes',
            'requester_email' => 'ari@example.test',
            'requester_position' => 'Organization President',
            'dean' => [
                'id_number' => '01-0001-0001',
                'email' => 'dean@example.test',
                'first_name' => 'Dana',
                'last_name' => 'Dean',
            ],
            'officers' => [
                'president' => [
                    'student_id' => '2026-0001',
                    'email' => 'ari@example.test',
                    'first_name' => 'Ari',
                    'last_name' => 'Reyes',
                ],
            ],
        ], $files);

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
        self::assertCount(1, $memberMetadata);
        self::assertSame('Original Members.pdf', $memberMetadata[0]['name']);
        self::assertStringStartsWith('member-', $memberMetadata[0]['slot']);
        self::assertSame('public', $memberMetadata[0]['storage_disk']);
        self::assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $memberMetadata[0]['source_version']);
        self::assertSame(hash('sha256', 'member bytes'), $memberMetadata[0]['source_fingerprint']);

        self::assertCount(8, ProcessedDocument::query()->get());
        self::assertCount(8, DocumentLibraryEntry::query()->get());
        $applicationFormAnalysis = ProcessedDocument::query()->where('document_field', 'application_form')->sole();
        $applicationFormEntry = DocumentLibraryEntry::query()->where('document_field', 'application_form')->sole();
        self::assertSame($applicationFormAnalysis->source_version, $applicationFormEntry->source_version);
        self::assertSame($applicationFormAnalysis->source_fingerprint, $applicationFormEntry->source_fingerprint);
        self::assertSame(
            'application_form.pdf',
            ProcessedDocument::query()->where('document_field', 'application_form')->value('original_filename'),
        );
        Queue::assertPushed(ProcessDocumentAnalysis::class, 8);
        Queue::assertPushed(ProcessDocumentAnalysis::class, fn (ProcessDocumentAnalysis $job): bool => $job->connection === 'database' && $job->queue === 'document-ai');
    }

    public function test_application_registration_failure_rolls_back_users_and_cleans_only_new_uploads(): void
    {
        Storage::disk('public')->put('existing/keep.pdf', 'existing bytes');
        Schema::drop('processed_documents');

        $files = [];
        foreach ([
            'adviser_commitment',
            'application_form',
            'certification_of_officers',
            'plan_of_activities',
            'constitution_bylaws',
            'certification_dean',
            'letter_of_application',
        ] as $field) {
            $files[$field] = UploadedFile::fake()->createWithContent("{$field}.pdf", "bytes:{$field}");
        }
        $files['list_of_members'] = [
            UploadedFile::fake()->createWithContent('Rollback Members.pdf', 'member bytes'),
        ];

        $response = $this->submit([
            'request_type' => 'apply',
            'application_type' => 'apply',
            'organization_name' => 'Rollback Society',
            'organization_acronym' => 'ROLL',
            'organization_type' => 'Mother Org',
            'requester_first_name' => 'Ari',
            'requester_last_name' => 'Reyes',
            'requester_email' => 'ari@example.test',
            'requester_position' => 'Organization President',
            'dean' => [
                'id_number' => '01-0001-0002',
                'email' => 'dean@example.test',
                'first_name' => 'Dana',
                'last_name' => 'Dean',
            ],
            'officers' => [
                'president' => [
                    'student_id' => '2026-0009',
                    'email' => 'ari@example.test',
                    'first_name' => 'Ari',
                    'last_name' => 'Reyes',
                ],
            ],
        ], $files);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(0, ApplyOrg::query()->count());
        self::assertSame(0, DocumentLibraryEntry::query()->count());
        self::assertSame(0, Schema::hasTable('users') ? User::query()->count() : 0);
        Storage::disk('public')->assertExists('existing/keep.pdf');
        self::assertSame(['existing/keep.pdf'], Storage::disk('public')->allFiles());
        Queue::assertNothingPushed();
    }

    public function test_application_passes_without_requester_position_and_with_empty_officer_cards(): void
    {
        $files = [];
        foreach ([
            'adviser_commitment',
            'application_form',
            'certification_of_officers',
            'plan_of_activities',
            'constitution_bylaws',
            'certification_dean',
            'letter_of_application',
        ] as $field) {
            $files[$field] = UploadedFile::fake()->createWithContent("{$field}.pdf", "bytes:{$field}");
        }
        $files['list_of_members'] = [
            UploadedFile::fake()->createWithContent('Flexible Members.pdf', 'member bytes'),
        ];

        $response = $this->submit([
            'request_type' => 'apply',
            'application_type' => 'apply',
            'organization_name' => 'Flexible Society',
            'organization_acronym' => 'FLEX',
            'organization_type' => 'Mother Org',
            'requester_first_name' => 'Ari',
            'requester_last_name' => 'Reyes',
            'requester_email' => 'ari@example.test',
            'requester_position' => '',
            'dean' => [
                'id_number' => '01-0001-0003',
                'email' => 'dean@example.test',
                'first_name' => 'Dana',
                'last_name' => 'Dean',
            ],
            'officers' => [
                'president' => [
                    'student_id' => '2026-0001',
                    'email' => 'ari@example.test',
                    'first_name' => 'Ari',
                    'last_name' => 'Reyes',
                ],
                'vice_president' => [
                    'student_id' => '2026-0002',
                    'email' => 'vp@example.test',
                    'first_name' => 'Vera',
                    'last_name' => 'Perez',
                ],
            ],
        ], $files);

        self::assertSame(200, $response->getStatusCode(), $response->getContent());
        $application = ApplyOrg::query()->sole();
        self::assertNull($application->requester_position);
        self::assertSame('Pending', $application->status);

        $requester = User::where('email', 'ari@example.test')->sole();
        self::assertSame('President', $requester->temp_position);

        self::assertTrue(User::where('email', 'vp@example.test')->exists());
        self::assertTrue(User::where('email', 'dean@example.test')->exists());
        self::assertSame(3, User::count());
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $files */
    private function submit(array $data, array $files)
    {
        $request = Request::create('/apply-org', 'POST', $data, [], $files);
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('User-Agent', 'Desktop Test Browser');

        return $this->app->make(ApplyOrgController::class)->submit($request);
    }

    private function createSchema(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
        });

        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('organization_name');
            $table->string('organization_acronym')->nullable();
            $table->string('organization_type');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('request_type')->nullable();
            $table->string('application_type')->nullable();
            $table->string('requester_first_name');
            $table->string('requester_last_name');
            $table->string('requester_email');
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
            $table->text('list_of_members')->nullable();
            $table->text('other_files')->nullable();
            $table->text('accomplishment_reports')->nullable();
            $table->unsignedInteger('current_step')->nullable();
            $table->string('status')->nullable();
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

        $this->createProcessedDocumentsTable();
    }

    private function createProcessedDocumentsTable(): void
    {
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
