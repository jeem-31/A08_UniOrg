<?php

namespace Tests\Feature;

use App\Casts\PostgresBoolean;
use App\Http\Controllers\Admin\RequiredSubmissionController;
use App\Http\Controllers\Osas\RequiredDocumentController;
use App\Jobs\ProcessDocumentAnalysis;
use App\Jobs\ReTagDocument;
use App\Models\ApplyOrg;
use App\Models\Department;
use App\Models\Organization;
use App\Models\OsasDocumentSubmission;
use App\Models\OsasRequiredDocument;
use App\Models\ProcessedDocument;
use App\Models\User;
use App\Services\OsasRequiredDocs\ReportSubmissionPipeline;
use App\ViewModels\CurrentDocumentAnalysisPresenter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class OsasRequiredDocumentSubmissionTest extends TestCase
{
    private User $administrator;

    private Organization $organization;

    private Department $department;

    private OsasRequiredDocument $requirement;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.default' => 'public']);
        Storage::fake('public');
        Queue::fake();
        $this->createSchema();

        $this->department = Department::query()->create([
            'department_name' => 'College of Testing',
            'department_acronym' => 'COT',
        ]);
        $this->organization = Organization::query()->create([
            'name' => 'Testing Society',
            'org_acronym' => 'TS',
            'type' => 'Mother Org',
            'department_id' => $this->department->id,
            'status' => 'Approved',
        ]);
        $this->administrator = User::query()->create([
            'student_id' => '2026-0001',
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

        // Bulk requirement: whole department, visible to the org.
        $this->requirement = OsasRequiredDocument::query()->create([
            'department_id' => $this->department->id,
            'organization_id' => null,
            'document_key' => OsasRequiredDocument::KEY_NARRATIVE,
            'description' => 'Submit your narrative report.',
            'is_active' => true,
            'created_by' => $this->administrator->id,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([
            'osas_document_submissions',
            'osas_required_documents',
            'document_library_entries',
            'processed_documents',
            'organization_user',
            'organization_settings',
            'organization_positions',
            'organization_role_permissions',
            'notifications',
            'apply_orgs',
            'organizations',
            'departments',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_admin_submit_registers_linker_analysis_and_queues_processing(): void
    {
        $response = $this->submit();

        self::assertSame(302, $response->getStatusCode());
        self::assertTrue(session()->has('success'));

        $submission = OsasDocumentSubmission::query()->sole();
        self::assertSame('submitted', $submission->status);
        self::assertSame(1, $submission->version);
        self::assertSame('public', $submission->storage_disk);
        Storage::disk('public')->assertExists($submission->file_path);

        // Internal linker row stays out of accreditation queues.
        $linker = ApplyOrg::query()->sole();
        self::assertSame((int) $this->organization->id, (int) $linker->organization_id);
        self::assertNull($linker->application_type);
        $slots = collect(json_decode((string) $linker->other_files, true));
        self::assertSame(
            ["reqdoc-{$this->requirement->id}-org-{$this->organization->id}"],
            $slots->pluck('slot')->all(),
        );

        $analysis = ProcessedDocument::query()->sole();
        self::assertSame('other_files', $analysis->document_field);
        self::assertSame("reqdoc-{$this->requirement->id}-org-{$this->organization->id}", $analysis->document_slot);
        self::assertSame('pending', $analysis->processing_status);
        self::assertSame((int) $analysis->getKey(), (int) $submission->fresh()->processed_document_id);

        Queue::assertPushed(ProcessDocumentAnalysis::class, 1);
    }

    public function test_second_submit_while_pending_is_blocked(): void
    {
        $this->submit();
        $response = $this->submit();

        self::assertSame(302, $response->getStatusCode());
        self::assertTrue(session()->has('error'));
        self::assertSame(1, OsasDocumentSubmission::query()->count());
    }

    public function test_reject_allows_resubmit_as_new_version_and_approve_marks_for_signature(): void
    {
        $this->submit();
        $first = OsasDocumentSubmission::query()->sole();

        $this->review($first, 'reject', 'Missing annex A.');
        self::assertSame('rejected', $first->fresh()->status);

        $this->submit('narrative v2 content');
        self::assertSame(2, OsasDocumentSubmission::query()->count());
        $second = OsasDocumentSubmission::query()->orderByDesc('version')->first();
        self::assertSame(2, $second->version);
        self::assertSame('submitted', $second->status);

        $this->review($second, 'approve');
        self::assertSame('approved_for_signature', $second->fresh()->status);
        self::assertNotNull($second->fresh()->reviewed_at);
    }

    public function test_submit_stamps_linker_with_current_school_year(): void
    {
        $this->submit();

        $pipeline = $this->app->make(ReportSubmissionPipeline::class);
        $expected = $pipeline->currentSchoolYear($this->organization);

        self::assertMatchesRegularExpression('/^\d{4}-\d{4}$/', (string) $expected);

        $linker = ApplyOrg::query()->sole();
        self::assertSame($expected, $linker->accreditation_year);
    }

    public function test_linkers_are_scoped_per_school_year(): void
    {
        $pipeline = $this->app->make(ReportSubmissionPipeline::class);

        $pipeline->linkerForOrganization($this->organization, '2025-2026');
        $pipeline->linkerForOrganization($this->organization);

        self::assertSame(2, ApplyOrg::query()->count());
        self::assertSame(
            ['2025-2026', $pipeline->currentSchoolYear($this->organization)],
            ApplyOrg::query()->orderBy('id')->pluck('accreditation_year')->all(),
        );
    }

    public function test_bulk_requirement_surfaces_pending_applications(): void
    {
        $department = Department::query()->create([
            'department_name' => 'College of Newcomers',
            'department_acronym' => 'CN',
        ]);
        $requirement = OsasRequiredDocument::query()->create([
            'department_id' => $department->id,
            'organization_id' => null,
            'document_key' => OsasRequiredDocument::KEY_NARRATIVE,
            'is_active' => true,
            'created_by' => $this->administrator->id,
        ]);
        ApplyOrg::query()->create([
            'organization_id' => null,
            'organization_name' => 'Incoming Club',
            'organization_acronym' => 'IC',
            'organization_type' => 'Mother Org',
            'department_id' => $department->id,
            'request_type' => 'apply',
            'application_type' => 'apply',
            'requester_first_name' => 'Rene',
            'requester_last_name' => 'Walsh',
            'requester_email' => 'rene@example.test',
            'status' => 'Pending',
        ]);

        $controller = new RequiredDocumentController;
        $view = $controller->submissions(
            $requirement,
            $this->app->make(ReportSubmissionPipeline::class),
            $this->app->make(CurrentDocumentAnalysisPresenter::class),
        );

        // No approved orgs in this department — only the pending application row.
        $rows = $view->getData()['rows'];
        self::assertCount(1, $rows);
        self::assertNull($rows[0]['organization']);
        self::assertSame('Incoming Club', $rows[0]['pending_application']->organization_name);
        self::assertNull($rows[0]['latest']);

        $index = $controller->index(Request::create('/osas/required-documents', 'GET'));
        $published = $index->getData()['requirements']->getCollection()
            ->firstWhere('id', $requirement->id);
        self::assertSame(0, $published->stats['orgs']);
        self::assertSame(1, $published->stats['pending']);
    }

    public function test_active_scope_uses_postgres_safe_boolean_comparison(): void
    {
        // Regression guard: where('is_active', true) compiles to "is_active" = 1,
        // which PostgreSQL rejects (42883) while SQLite silently accepts.
        $sql = OsasRequiredDocument::query()->active()->toSql();

        self::assertStringContainsString('"is_active" = TRUE', $sql);
        self::assertStringNotContainsString('"is_active" = 1', $sql);
    }

    public function test_is_active_uses_postgres_boolean_cast_for_writes(): void
    {
        // Regression guard: plain 'boolean' cast binds true as integer 1 on
        // INSERT/UPDATE, which PostgreSQL rejects (42804).
        self::assertSame(
            PostgresBoolean::class,
            (new OsasRequiredDocument)->getCasts()['is_active']
        );
        self::assertTrue($this->requirement->is_active);
    }

    public function test_search_matches_auto_tags_and_summaries_case_insensitively(): void
    {
        $this->submit();

        $analysis = ProcessedDocument::query()->sole();
        $analysis->forceFill([
            'tags' => ['budget', 'narrative'],
            'summary' => 'Quarterly financial highlights for the organization.',
        ])->save();

        $idsFor = function (string $term): array {
            $request = Request::create('/osas/required-documents', 'GET', ['q' => $term]);
            $request->setLaravelSession(app('session')->driver());
            $view = $this->app->make(RequiredDocumentController::class)->index($request);

            return $view->getData()['requirements']->getCollection()->pluck('id')->all();
        };

        self::assertSame([$this->requirement->id], $idsFor('budget'));
        self::assertSame([$this->requirement->id], $idsFor('BUDGET'));
        self::assertSame([$this->requirement->id], $idsFor('quarterly financial'));
        self::assertSame([], $idsFor('zzz-no-such-tag'));
    }

    public function test_index_filters_by_department_type_and_status(): void
    {
        $osas = User::query()->create([
            'student_id' => '00-0000-0002',
            'first_name' => 'Osa',
            'last_name' => 'Admin',
            'email' => 'osas-filter@example.test',
            'role' => 'osas_admin',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        Auth::login($osas);
        $otherDept = Department::query()->create([
            'department_name' => 'College of Filters',
            'department_acronym' => 'CF',
        ]);
        $archived = OsasRequiredDocument::query()->create([
            'department_id' => $otherDept->id,
            'organization_id' => null,
            'document_key' => OsasRequiredDocument::KEY_ACCOMPLISHMENT,
            'is_active' => false,
            'created_by' => $osas->id,
        ]);

        $idsFor = function (array $query): array {
            $request = Request::create('/osas/required-documents', 'GET', $query);
            $request->setLaravelSession(app('session')->driver());
            $view = $this->app->make(RequiredDocumentController::class)->index($request);

            return $view->getData()['requirements']->getCollection()->pluck('id')->all();
        };

        // Department filter isolates the other department's requirement.
        self::assertSame([$archived->id], $idsFor(['department_id' => (string) $otherDept->id]));

        // Status filter isolates archived rows.
        self::assertSame([$archived->id], $idsFor(['status' => 'archived']));

        // Document type filter isolates the accomplishment requirement.
        self::assertSame([$archived->id], $idsFor(['document_key' => OsasRequiredDocument::KEY_ACCOMPLISHMENT]));

        // Text search matches the custom title of an "other" requirement.
        $custom = OsasRequiredDocument::query()->create([
            'department_id' => $this->department->id,
            'organization_id' => null,
            'document_key' => OsasRequiredDocument::KEY_OTHER,
            'custom_title' => 'Mid-Year Special Filing',
            'is_active' => true,
            'created_by' => $osas->id,
        ]);
        self::assertSame([$custom->id], $idsFor(['q' => 'Special Filing']));

        // Unknown filter values are ignored instead of erroring.
        self::assertCount(3, $idsFor(['status' => 'bogus', 'document_key' => 'nope']));
    }

    public function test_retag_endpoint_queues_job_for_completed_analysis_and_conflicts_otherwise(): void
    {
        $this->createRenderSupportSchema();
        $osas = User::query()->create([
            'student_id' => '00-0000-0003',
            'first_name' => 'Osa',
            'last_name' => 'Admin',
            'email' => 'osas-retag@example.test',
            'role' => 'osas_admin',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->submit();
        $analysis = ProcessedDocument::query()->sole();
        $linker = ApplyOrg::query()->sole();

        // Pending analysis has no current output → 409, nothing dispatched.
        $this->actingAs($osas)
            ->post(route('osas.apply-org.analyses.retag', [$linker, $analysis]))
            ->assertStatus(409);
        Queue::assertNotPushed(ReTagDocument::class);

        $analysis->forceFill([
            'processing_status' => ProcessedDocument::STATUS_COMPLETED,
            'output_source_version' => $analysis->source_version,
            'summary' => 'Summary.',
            'tags' => ['old-tag'],
        ])->save();

        $baseline = $analysis->fresh()->updated_at?->toIso8601String();

        $this->actingAs($osas)
            ->post(route('osas.apply-org.analyses.retag', [$linker, $analysis->fresh()]))
            ->assertStatus(202)
            ->assertJsonPath('tags_updated_at', $baseline);
        Queue::assertPushed(
            ReTagDocument::class,
            static fn (ReTagDocument $job): bool => $job->analysisId === (int) $analysis->getKey()
                && $job->sourceVersion === (string) $analysis->source_version
        );

        $this->actingAs($osas)
            ->getJson(route('osas.apply-org.analyses.status', [$linker, $analysis->fresh()]))
            ->assertOk()
            ->assertJsonPath('updated_at', $baseline);
    }

    public function test_osas_pages_render_successfully(): void
    {
        $this->createRenderSupportSchema();
        $osas = User::query()->create([
            'student_id' => '00-0000-0001',
            'first_name' => 'Osa',
            'last_name' => 'Admin',
            'email' => 'osas@example.test',
            'role' => 'osas_admin',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        ApplyOrg::query()->create([
            'organization_id' => null,
            'organization_name' => 'Incoming Club',
            'organization_acronym' => 'IC',
            'organization_type' => 'Mother Org',
            'department_id' => $this->department->id,
            'request_type' => 'apply',
            'application_type' => 'apply',
            'requester_first_name' => 'Rene',
            'requester_last_name' => 'Walsh',
            'requester_email' => 'rene@example.test',
            'status' => 'Pending',
        ]);

        $this->actingAs($osas)->get(route('osas.required-documents.index'))->assertOk();
        $this->actingAs($osas)
            ->get(route('osas.required-documents.submissions', $this->requirement))
            ->assertOk()
            ->assertSee('Applied');
    }

    public function test_admin_page_renders_successfully(): void
    {
        $this->createRenderSupportSchema();

        $this->actingAs($this->administrator)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get(route('admin.required-submissions.index'))
            ->assertOk();
    }

    private function submit(string $content = 'narrative content'): mixed
    {
        $file = UploadedFile::fake()->createWithContent('narrative.pdf', "%PDF-1.4\n{$content}\n%%EOF");
        $request = Request::create('/admin/required-submissions', 'POST', [
            'required_document_id' => $this->requirement->id,
        ], [], ['document' => $file]);
        $request->headers->set('Accept', 'text/html');
        $request->setLaravelSession(app('session')->driver());

        return $this->app->make(RequiredSubmissionController::class)->store($request);
    }

    private function review(OsasDocumentSubmission $submission, string $action, ?string $remarks = null): mixed
    {
        $request = Request::create('/review', 'POST', array_filter([
            'action' => $action,
            'remarks' => $remarks,
        ]));
        $request->setLaravelSession(app('session')->driver());

        return $this->app->make(RequiredDocumentController::class)->review($request, $submission);
    }

    private function createSchema(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('department_name');
            $table->string('department_acronym')->nullable();
            $table->timestamps();
        });

        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('org_acronym')->nullable();
            $table->string('type');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('student_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('role')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->nullable();
            $table->string('membership_status')->nullable();
            $table->unsignedBigInteger('management_version')->nullable();
            $table->timestamps();
        });

        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('organization_name');
            $table->string('organization_acronym')->nullable();
            $table->string('organization_type');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('request_type')->nullable();
            $table->string('application_type')->nullable();
            $table->boolean('auto_generated')->nullable();
            $table->string('accreditation_year')->nullable();
            $table->string('requester_first_name');
            $table->string('requester_last_name');
            $table->string('requester_email');
            $table->string('requester_position')->nullable();
            $table->text('other_files')->nullable();
            $table->unsignedInteger('current_step')->nullable();
            $table->string('status')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('processed_documents', function (Blueprint $table): void {
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

        Schema::create('osas_required_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('document_key', 60);
            $table->string('custom_title')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('osas_document_submissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('required_document_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('submitted_by');
            $table->string('file_path');
            $table->string('storage_disk', 60);
            $table->string('original_filename');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status', 40)->default('submitted');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('processed_document_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Tables touched by the OSAS/admin layout composers when pages render.
     */
    private function createRenderSupportSchema(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->boolean('has_announcements')->nullable();
            $table->boolean('has_events')->nullable();
            $table->boolean('has_payments')->nullable();
            $table->boolean('has_elections')->nullable();
            $table->boolean('has_library')->nullable();
            $table->boolean('has_edushare')->nullable();
            $table->boolean('has_documents')->nullable();
            $table->boolean('has_chatbot')->nullable();
            $table->boolean('has_complaints')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_positions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('organization_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('role');
            $table->string('module');
            $table->boolean('is_granted')->default(false);
            $table->timestamps();
        });
    }
}
