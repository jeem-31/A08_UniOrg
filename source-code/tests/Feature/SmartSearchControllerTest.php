<?php

namespace Tests\Feature;

use App\Http\Controllers\Osas\SmartSearchController;
use App\Models\ProcessedDocument;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** **Validates: Requirements 1.1, 1.2, 1.3, 1.6, 4.8, 7.7, 11.5** */
final class SmartSearchControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        $this->seedLibrary();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS pg_temp.document_processing_attempts, pg_temp.processed_documents, pg_temp.document_library_entries, pg_temp.apply_orgs, pg_temp.organizations, pg_temp.departments CASCADE');
        } else {
            Schema::dropIfExists('document_processing_attempts');
            Schema::dropIfExists('processed_documents');
            Schema::dropIfExists('document_library_entries');
            Schema::dropIfExists('apply_orgs');
            Schema::dropIfExists('organizations');
            Schema::dropIfExists('organization_settings');
            Schema::dropIfExists('departments');
        }

        parent::tearDown();
    }
    public function test_default_request_returns_organization_folders_and_authorized_options(): void
    {
        $before = $this->recordCounts();
        $view = $this->request();
        $data = $view->getData();

        self::assertSame('root', $data['library']->level);
        self::assertSame(
            ['Alpha Society', 'Beta Guild'],
            array_map(static fn ($folder): string => $folder->label, $data['library']->folders),
        );
        self::assertSame([1, 1], array_map(
            static fn ($folder): int => $folder->documentCount,
            $data['library']->folders,
        ));
        self::assertSame(
            ['https://example.test/alpha-society-logo.png', null],
            array_map(
                static fn ($folder): ?string => $folder->logoUrl,
                $data['library']->folders,
            ),
        );
        self::assertSame(['CCS'], $data['filterOptions']['department']);
        self::assertSame([], $data['library']->documents);
        self::assertSame($before, $this->recordCounts());
        Queue::assertNothingPushed();
    }

    public function test_non_empty_keyword_returns_cross_folder_documents_once(): void
    {
        $view = $this->request(['q' => 'shared needle']);
        $library = $view->getData()['library'];

        self::assertSame('search', $library->level);
        self::assertSame(1, $library->pagination->total);
        self::assertSame([1], array_map(
            static fn ($document): int => $document->id,
            $library->documents,
        ));
        self::assertSame([], $library->folders);
        Queue::assertNothingPushed();
    }

    public function test_pending_application_documents_do_not_create_a_folder(): void
    {
        DB::table('apply_orgs')->insert([
            'id' => 3, 'organization_id' => null, 'department_id' => 1,
            'organization_name' => 'Pending Society', 'organization_acronym' => 'PS',
            'application_type' => 'apply', 'request_type' => 'apply',
            'status' => 'Pending', 'created_at' => '2026-06-01 00:00:00',
        ]);
        DB::table('document_library_entries')->insert([
            'id' => 3,
            'apply_org_id' => 3,
            'document_field' => 'application_form',
            'document_slot' => 'primary',
            'requirement_label' => 'Application Form',
            'original_filename' => 'pending.pdf',
            'storage_disk' => 'private',
            'file_path' => 'documents/3.pdf',
            'source_version' => 'v3',
            'source_fingerprint' => str_repeat('3', 64),
        ]);

        $view = $this->request();
        $folders = $view->getData()['library']->folders;

        self::assertSame(['Alpha Society', 'Beta Guild'], array_map(
            static fn ($folder): string => $folder->label,
            $folders,
        ));
    }

    /** @param array<string, mixed> $query */
    private function request(array $query = []): View
    {
        $request = Request::create('/osas/smart-search', 'GET', $query);
        $user = new User(['role' => 'osas_admin']);
        $request->setUserResolver(static fn (): User => $user);
        $response = app(SmartSearchController::class)->index($request);

        self::assertInstanceOf(View::class, $response);

        return $response;
    }

    /** @return array{applications: int, entries: int, analyses: int} */
    private function recordCounts(): array
    {
        return [
            'applications' => DB::table('apply_orgs')->count(),
            'entries' => DB::table('document_library_entries')->count(),
            'analyses' => DB::table('processed_documents')->count(),
        ];
    }
    private function seedLibrary(): void
    {
        DB::table('departments')->insert(['id' => 1, 'department_name' => 'CCS']);
        DB::table('organizations')->insert([
            ['id' => 1, 'name' => 'Alpha Society', 'org_acronym' => 'AS'],
            ['id' => 2, 'name' => 'Beta Guild', 'org_acronym' => 'BG'],
        ]);
        DB::table('organization_settings')->insert([
            'organization_id' => 1,
            'logo_url' => 'https://example.test/alpha-society-logo.png',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        DB::table('apply_orgs')->insert([
            [
                'id' => 1, 'organization_id' => 1, 'department_id' => 1,
                'organization_name' => 'Alpha Society', 'organization_acronym' => 'AS',
                'application_type' => 'apply', 'request_type' => 'apply',
                'status' => 'Approved', 'created_at' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 2, 'organization_id' => 2, 'department_id' => 1,
                'organization_name' => 'Beta Guild', 'organization_acronym' => 'BG',
                'application_type' => 'renew', 'request_type' => 'request',
                'status' => 'Approved', 'created_at' => '2025-01-01 00:00:00',
            ],
        ]);

        foreach ([1, 2] as $id) {
            DB::table('document_library_entries')->insert([
                'id' => $id,
                'apply_org_id' => $id,
                'document_field' => 'application_form',
                'document_slot' => 'primary',
                'requirement_label' => 'Application Form',
                'original_filename' => "document-{$id}.pdf",
                'storage_disk' => 'private',
                'file_path' => "documents/{$id}.pdf",
                'source_version' => "v{$id}",
                'source_fingerprint' => str_repeat((string) $id, 64),
            ]);
        }

        DB::table('processed_documents')->insert([
            'id' => 10,
            'apply_org_id' => 1,
            'document_field' => 'application_form',
            'document_slot' => 'primary',
            'storage_disk' => 'private',
            'file_path' => 'documents/1.pdf',
            'source_version' => 'v1',
            'source_fingerprint' => str_repeat('1', 64),
            'association_ready_at' => '2026-01-01 00:00:00',
            'output_source_version' => 'v1',
            'processing_status' => ProcessedDocument::STATUS_COMPLETED,
            'processed_at' => '2026-01-02 00:00:00',
            'document_type' => 'application',
            'document_type_name' => 'Application',
            'summary' => 'A shared needle appears here.',
            'tags' => json_encode(['shared needle'], JSON_THROW_ON_ERROR),
            'key_information' => json_encode(['school_year' => '2025-2026'], JSON_THROW_ON_ERROR),
            'extracted_text' => 'Current extracted content.',
        ]);
    }
    private function createSchema(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('department_name');
            $table->string('department_acronym', 20)->nullable();
        });
        Schema::create('organizations', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('name');
            $table->string('org_acronym')->nullable();
        });
        Schema::create('organization_settings', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('logo_url')->nullable();
            $table->timestamps();
        });
        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('organization_acronym')->nullable();
            $table->string('application_type')->nullable();
            $table->string('request_type')->nullable();
            $table->string('status');
            $table->string('accreditation_year')->nullable();
            $table->timestamp('created_at')->nullable();
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
        });
        Schema::create('processed_documents', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('apply_org_id');
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('storage_disk');
            $table->string('file_path');
            $table->string('source_version')->nullable();
            $table->string('source_fingerprint', 64)->nullable();
            $table->timestamp('association_ready_at')->nullable();
            $table->string('output_source_version')->nullable();
            $table->string('processing_status');
            $table->timestamp('processed_at')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_type_name')->nullable();
            $table->text('summary')->nullable();
            $table->json('tags')->nullable();
            $table->json('key_information')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->unique(['apply_org_id', 'document_field', 'document_slot']);
        });
    }
}
