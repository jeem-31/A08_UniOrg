<?php

namespace Tests\Feature;

use App\Http\Controllers\Osas\OsasController;
use App\Models\ApplyOrg;
use App\Models\ProcessedDocument;
use App\Models\User;
use App\ViewModels\CurrentDocumentAnalysisPresenter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/** **Validates: Requirements 4.9, 8.6, 8.7, 9.1, 9.4, 9.5** */
final class CurrentDocumentAnalysisQueriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS pg_temp.document_processing_attempts, pg_temp.processed_documents, pg_temp.events, pg_temp.notifications, pg_temp.apply_orgs, pg_temp.organization_settings, pg_temp.organizations, pg_temp.departments, pg_temp.users CASCADE');
        } else {
            Schema::dropIfExists('document_processing_attempts');
            Schema::dropIfExists('processed_documents');
            Schema::dropIfExists('events');
            Schema::dropIfExists('notifications');
            Schema::dropIfExists('apply_orgs');
            Schema::dropIfExists('organization_settings');
            Schema::dropIfExists('organizations');
            Schema::dropIfExists('departments');
            Schema::dropIfExists('users');
        }

        parent::tearDown();
    }

    public function test_show_eager_loads_current_analyses_and_read_path_dispatches_nothing(): void
    {
        $application = $this->application('documents/current.pdf');
        $current = $this->analysis($application, ProcessedDocument::STATUS_COMPLETED, true);

        $view = app(OsasController::class)->showApplyOrg(
            $application,
            app(CurrentDocumentAnalysisPresenter::class),
        );

        self::assertTrue($view->getData()['request']->relationLoaded('processedDocuments'));
        self::assertSame($current->id, $view->getData()['documentAnalyses'][0]['analysis_id']);
        self::assertSame('completed', $view->getData()['analysisState']['status']);
        Queue::assertNothingPushed();
    }

    public function test_application_list_aggregates_actual_current_statuses(): void
    {
        $application = $this->application('documents/list.pdf');
        $this->analysis($application, ProcessedDocument::STATUS_FAILED, false);

        $view = app(OsasController::class)->applyOrgs(
            Request::create('/osas/apply-org', 'GET'),
            app(CurrentDocumentAnalysisPresenter::class),
        );
        $listed = $view->getData()['requests']->getCollection()->first();

        self::assertSame('failed', $listed->analysis_state['status']);
        self::assertSame(1, $listed->analysis_state['counts']['failed']);
        self::assertSame(0, $listed->analysis_state['output_available']);
        Queue::assertNothingPushed();
    }

    public function test_application_list_renders_for_empty_and_all_persisted_analysis_states(): void
    {
        $this->application('documents/no-analysis.pdf');

        foreach ([
            ProcessedDocument::STATUS_PENDING,
            ProcessedDocument::STATUS_PROCESSING,
            ProcessedDocument::STATUS_COMPLETED,
            ProcessedDocument::STATUS_FAILED,
        ] as $status) {
            $application = $this->application("documents/{$status}.pdf");
            $this->analysis(
                $application,
                $status,
                $status === ProcessedDocument::STATUS_COMPLETED,
            );
        }

        $this->actingAs($this->osasUser());
        $html = app(OsasController::class)->applyOrgs(
            Request::create('/osas/apply-org', 'GET'),
            app(CurrentDocumentAnalysisPresenter::class),
        )->render();

        self::assertStringContainsString('No Documents', $html);
        self::assertStringContainsString('Pending (1)', $html);
        self::assertStringContainsString('Processing (1)', $html);
        self::assertStringContainsString('Completed (1/1)', $html);
        self::assertStringContainsString('Failed (1)', $html);
        Queue::assertNothingPushed();
    }

    public function test_current_output_scope_returns_only_completed_current_source_output(): void
    {
        $application = $this->application('documents/search.pdf');
        $current = $this->analysis($application, ProcessedDocument::STATUS_COMPLETED, true);
        $this->analysis($application, ProcessedDocument::STATUS_COMPLETED, false, 'constitution_bylaws');
        $this->analysis($application, ProcessedDocument::STATUS_FAILED, false, 'financial_report');

        self::assertSame(
            [$current->id],
            ProcessedDocument::withCurrentOutput()->pluck('id')->all(),
        );
        Queue::assertNothingPushed();
    }

    public function test_document_area_renders_persisted_states_actions_metadata_and_multiple_membership_slots_without_scanning(): void
    {
        $application = $this->application('documents/pending.pdf');
        $application->forceFill([
            'constitution_bylaws' => 'documents/processing.pdf',
            'adviser_commitment' => 'documents/completed.pdf',
            'application_form' => 'documents/refreshing.pdf',
            'certification_of_officers' => 'documents/refresh-failed.pdf',
            'plan_of_activities' => 'documents/changed.pdf',
            'financial_report' => 'documents/unchanged.pdf',
        ])->save();
        $firstMembershipVersion = (string) Str::uuid();
        $secondMembershipVersion = (string) Str::uuid();
        $application->forceFill(['list_of_members' => json_encode([
            [
                'path' => 'documents/members-failed.pdf',
                'name' => 'Unreadable Members.pdf',
                'slot' => 'member-failed',
                'storage_disk' => storage_disk(),
                'source_version' => $firstMembershipVersion,
                'source_fingerprint' => hash('sha256', $firstMembershipVersion),
            ],
            [
                'path' => 'documents/members-completed.pdf',
                'name' => 'Verified Members.pdf',
                'slot' => 'member-completed',
                'storage_disk' => storage_disk(),
                'source_version' => $secondMembershipVersion,
                'source_fingerprint' => hash('sha256', $secondMembershipVersion),
            ],
        ], JSON_THROW_ON_ERROR)])->save();

        $processedAt = now()->setDate(2026, 7, 8)->setTime(9, 10, 11);
        $pending = $this->analysis($application, ProcessedDocument::STATUS_PENDING, false);
        $processing = $this->analysis($application, ProcessedDocument::STATUS_PROCESSING, false, 'constitution_bylaws');
        $completed = $this->analysis($application, ProcessedDocument::STATUS_COMPLETED, true, 'adviser_commitment', [
            'original_filename' => 'True Completed Filename.pdf',
            'processed_at' => $processedAt,
            'summary_processed_at' => $processedAt,
            'processing_duration_ms' => 321,
            'document_type_name' => 'Adviser Commitment',
            'tags' => ['adviser commitment', 'review'],
        ]);
        $refreshing = $this->analysis($application, ProcessedDocument::STATUS_COMPLETED, true, 'application_form', [
            'resummarization_status' => 'processing',
        ]);
        $refreshFailed = $this->analysis($application, ProcessedDocument::STATUS_COMPLETED, true, 'certification_of_officers', [
            'resummarization_status' => 'failed',
            'resummarization_error_message' => 'The summary service is temporarily unavailable. Please try again.',
        ]);
        $changed = $this->analysis($application, ProcessedDocument::STATUS_COMPLETED, true, 'plan_of_activities', [
            'last_resummarization_changed' => true,
            'summary_processed_at' => $processedAt,
        ]);
        $unchanged = $this->analysis($application, ProcessedDocument::STATUS_COMPLETED, true, 'financial_report', [
            'last_resummarization_changed' => false,
            'summary_processed_at' => $processedAt,
        ]);
        $failed = $this->analysis($application, ProcessedDocument::STATUS_FAILED, false, 'list_of_members', [
            'document_slot' => 'member-failed',
            'file_path' => 'documents/members-failed.pdf',
            'original_filename' => 'Unreadable Members.pdf',
            'source_version' => $firstMembershipVersion,
            'source_fingerprint' => hash('sha256', $firstMembershipVersion),
            'error_message' => 'The document could not be read. Please upload a readable file or retry.',
        ]);
        $secondMembership = $this->analysis($application, ProcessedDocument::STATUS_COMPLETED, true, 'list_of_members', [
            'document_slot' => 'member-completed',
            'file_path' => 'documents/members-completed.pdf',
            'original_filename' => 'Verified Members.pdf',
            'source_version' => $secondMembershipVersion,
            'source_fingerprint' => hash('sha256', $secondMembershipVersion),
        ]);

        $this->actingAs($this->osasUser());
        $firstRender = $this->renderDocumentArea($application);
        $secondRender = $this->renderDocumentArea($application);

        self::assertSame($firstRender, $secondRender);
        self::assertStringNotContainsString('AI-generated information for OSAS review only.', $firstRender);
        self::assertStringContainsString('True Completed Filename.pdf', $firstRender);
        self::assertStringContainsString('Adviser Commitment', $firstRender);
        self::assertStringContainsString('adviser commitment', $firstRender);
        self::assertStringContainsString('"processing_duration_ms":321', $firstRender);
        self::assertStringContainsString($processedAt->toIso8601String(), $firstRender);
        self::assertStringContainsString('The document could not be read. Please upload a readable file or retry.', $firstRender);
        self::assertStringContainsString('The summary service is temporarily unavailable. Please try again.', $firstRender);
        self::assertStringContainsString('Latest re-summarization changed the current summary.', $firstRender);
        self::assertStringContainsString('Latest re-summarization did not change the current summary.', $firstRender);
        self::assertStringContainsString('Unreadable Members.pdf', $firstRender);
        self::assertStringContainsString('Verified Members.pdf', $firstRender);
        self::assertStringNotContainsString('Scan with AI', $firstRender);
        self::assertStringNotContainsString('Scan All with AI', $firstRender);
        self::assertStringNotContainsString('processDocument(', $firstRender);
        self::assertStringNotContainsString('processAllDocuments(', $firstRender);
        self::assertStringNotContainsString('scanDocument(', $firstRender);

        $this->assertDocumentActions($firstRender, $pending, ['View Document'], ['View Summary', 'Retry Processing', 'Re-summarize']);
        $this->assertDocumentActions($firstRender, $processing, ['View Document'], ['View Summary', 'Retry Processing', 'Re-summarize']);
        $this->assertDocumentActions($firstRender, $completed, ['View Document', 'View Summary', 'Re-summarize'], ['Retry Processing']);
        $this->assertDocumentActions($firstRender, $refreshing, ['View Document', 'View Summary', 'Refreshing Summary'], ['Retry Processing', '> Re-summarize<']);
        $this->assertDocumentActions($firstRender, $refreshFailed, ['View Document', 'View Summary', 'Re-summarize'], ['Retry Processing']);
        $this->assertDocumentActions($firstRender, $changed, ['View Document', 'View Summary', 'Re-summarize'], ['Retry Processing']);
        $this->assertDocumentActions($firstRender, $unchanged, ['View Document', 'View Summary', 'Re-summarize'], ['Retry Processing']);
        $this->assertDocumentActions($firstRender, $failed, ['View Document', 'Retry Processing'], ['View Summary', 'Re-summarize']);
        $this->assertDocumentActions($firstRender, $secondMembership, ['View Document', 'View Summary', 'Re-summarize'], ['Retry Processing']);

        Queue::assertNothingPushed();
        foreach ([$pending, $processing, $completed, $refreshing, $refreshFailed, $changed, $unchanged, $failed, $secondMembership] as $analysis) {
            self::assertSame($analysis->processing_status, $analysis->fresh()->processing_status);
        }
    }

    private function renderDocumentArea(ApplyOrg $application): string
    {
        return app(OsasController::class)->showApplyOrg(
            $application->fresh(),
            app(CurrentDocumentAnalysisPresenter::class),
        )->render();
    }

    /** @param list<string> $visible @param list<string> $hidden */
    private function assertDocumentActions(string $html, ProcessedDocument $analysis, array $visible, array $hidden): void
    {
        $card = strstr($html, 'id="analysis-card-'.$analysis->id.'"');
        self::assertNotFalse($card, "Analysis card {$analysis->id} was not rendered.");
        $card = substr($card, 0, (int) strpos($card, '</article>'));

        foreach ($visible as $action) {
            self::assertStringContainsString($action, $card, "Expected {$action} for analysis {$analysis->id}.");
        }
        foreach ($hidden as $action) {
            self::assertStringNotContainsString($action, $card, "Did not expect {$action} for analysis {$analysis->id}.");
        }
    }

    private function osasUser(): User
    {
        $user = (new User)->forceFill([
            'first_name' => 'OSAS',
            'last_name' => 'Reviewer',
            'email' => 'osas-reviewer@example.test',
            'password' => 'not-used',
            'role' => 'osas_admin',
        ]);
        $user->save();

        return $user;
    }

    private function application(string $path): ApplyOrg
    {
        $application = (new ApplyOrg)->forceFill([
            'organization_name' => 'Query Test Organization',
            'organization_acronym' => 'QTO',
            'requester_first_name' => 'Query',
            'requester_last_name' => 'Tester',
            'requester_email' => 'query@example.test',
            'request_type' => 'apply',
            'status' => 'Pending',
            'department_id' => 1,
            'letter_of_application' => $path,
        ]);
        $application->save();

        return $application;
    }

    private function analysis(
        ApplyOrg $application,
        string $status,
        bool $currentOutput,
        string $field = 'letter_of_application',
        array $overrides = [],
    ): ProcessedDocument {
        $sourceVersion = $overrides['source_version'] ?? (string) Str::uuid();
        $path = $field === 'letter_of_application'
            ? $application->letter_of_application
            : "documents/{$field}.pdf";
        if ($field !== 'letter_of_application' && $field !== 'list_of_members') {
            $application->forceFill([$field => $path])->save();
        }

        $analysis = (new ProcessedDocument)->forceFill(array_replace([
            'apply_org_id' => $application->id,
            'document_field' => $field,
            'document_slot' => 'primary',
            'original_filename' => basename($path),
            'file_path' => $path,
            'storage_disk' => storage_disk(),
            'source_version' => $sourceVersion,
            'source_fingerprint' => hash('sha256', $sourceVersion),
            'association_ready_at' => now(),
            'processing_status' => $status,
            'output_source_version' => $currentOutput ? $sourceVersion : (string) Str::uuid(),
            'document_type' => 'application_letter',
            'document_type_name' => 'Application Letter',
            'summary' => 'Persisted searchable summary.',
            'tags' => ['application letter'],
            'key_information' => ['school_year' => '2026-2027'],
            'extracted_text' => 'Persisted extracted text.',
            'processed_at' => $status === ProcessedDocument::STATUS_COMPLETED ? now() : null,
            'summary_version' => $status === ProcessedDocument::STATUS_COMPLETED ? 1 : 0,
            'summary_processed_at' => $status === ProcessedDocument::STATUS_COMPLETED ? now() : null,
            'resummarization_status' => 'idle',
            'error_message' => $status === ProcessedDocument::STATUS_FAILED ? 'Safe failure.' : null,
        ], $overrides));
        $analysis->save();

        return $analysis;
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('apply_org_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });
        Schema::create('events', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('osas_status')->nullable();
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
        Schema::create('departments', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('department_name')->nullable();
            $table->timestamps();
        });
        Schema::create('organizations', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
        Schema::create('organization_settings', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('logo_url')->nullable();
        });
        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('organization_acronym')->nullable();
            $table->string('requester_first_name')->nullable();
            $table->string('requester_last_name')->nullable();
            $table->string('requester_email')->nullable();
            $table->string('request_type')->nullable();
            $table->string('status')->nullable();
            foreach ([
                'accreditation_form', 'letter_of_application', 'constitution_bylaws',
                'adviser_commitment', 'application_form', 'certification_of_officers',
                'plan_of_activities', 'certification_dean', 'financial_report',
                'accomplishment_reports', 'list_of_members',
            ] as $field) {
                $table->text($field)->nullable();
            }
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
            $table->string('processing_status');
            $table->string('output_source_version')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_type_name')->nullable();
            $table->text('summary')->nullable();
            $table->json('tags')->nullable();
            $table->json('key_information')->nullable();
            $table->text('extracted_text')->nullable();
            $table->unsignedBigInteger('processing_duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('summary_version')->default(0);
            $table->timestamp('summary_processed_at')->nullable();
            $table->string('resummarization_status')->default('idle');
            $table->text('resummarization_error_message')->nullable();
            $table->boolean('last_resummarization_changed')->nullable();
            $table->timestamps();
        });
    }
}
