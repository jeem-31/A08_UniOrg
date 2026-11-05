<?php

namespace Tests\Feature;

use App\Models\ApplyOrg;
use App\Models\DocumentLibraryEntry;
use App\Models\ProcessedDocument;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/** **Validates: Requirements 4.5, 4.9, 4.10, 8.2, 8.3, 8.4, 8.10** */
final class DocumentLibrarySourceRouteTest extends TestCase
{
    private const DISK = 'library-source-test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        Storage::fake(self::DISK);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('processed_documents');
        Schema::dropIfExists('document_library_entries');
        Schema::dropIfExists('apply_orgs');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_current_unprocessed_entry_streams_privately_from_fresh_descriptor(): void
    {
        [$application, $entry] = $this->currentEntry();
        Storage::disk(self::DISK)->put('documents/current.pdf', 'private-source-bytes');

        $response = $this->actingAs($this->user('osas_admin'))->get(
            route('osas.apply-org.library-documents.source', [$application, $entry]),
        );

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        self::assertNull($response->headers->get('Location'));
        self::assertSame('private-source-bytes', $response->streamedContent());
    }

    public function test_scoped_and_fresh_authorization_deny_cross_application_and_replaced_sources(): void
    {
        [$application, $entry] = $this->currentEntry();
        [$otherApplication] = $this->currentEntry('documents/other.pdf');
        $this->actingAs($this->user('osas_admin'));

        $crossApplication = $this->getJson(route(
            'osas.apply-org.library-documents.source',
            [$otherApplication, $entry],
        ));
        $crossApplication->assertNotFound();

        $application->forceFill([
            'application_form' => $this->source('documents/replacement.pdf'),
        ])->save();
        $stale = $this->getJson(route(
            'osas.apply-org.library-documents.source',
            [$application, $entry],
        ));
        $stale->assertNotFound();
        self::assertStringNotContainsString('documents/', $stale->getContent());
        self::assertStringNotContainsString(self::DISK, $stale->getContent());
    }

    public function test_current_analysis_is_redirected_to_existing_authorized_source_route(): void
    {
        [$application, $entry] = $this->currentEntry();
        $analysis = (new ProcessedDocument)->forceFill([
            'apply_org_id' => $application->id,
            'document_field' => 'application_form',
            'document_slot' => 'primary',
            'original_filename' => 'Current Document.pdf',
            'storage_disk' => self::DISK,
            'file_path' => 'documents/current.pdf',
            'source_version' => 'version-1',
            'source_fingerprint' => str_repeat('a', 64),
            'association_ready_at' => now(),
            'processing_status' => ProcessedDocument::STATUS_PENDING,
        ]);
        $analysis->save();

        $response = $this->actingAs($this->user('osas_admin'))->get(
            route('osas.apply-org.library-documents.source', [$application, $entry]),
        );

        $response->assertRedirect(route('osas.apply-org.analyses.source', [$application, $analysis]));
        self::assertStringNotContainsString('/storage/', (string) $response->headers->get('Location'));
    }

    public function test_storage_exceptions_are_caught_without_descriptor_disclosure(): void
    {
        [$application, $entry] = $this->currentEntry();
        Storage::shouldReceive('disk')->once()->with(self::DISK)
            ->andThrow(new RuntimeException('private documents/current.pdf failure'));

        $response = $this->actingAs($this->user('osas_admin'))->getJson(
            route('osas.apply-org.library-documents.source', [$application, $entry]),
        );

        $response->assertNotFound()->assertExactJson([
            'message' => 'The requested document is unavailable.',
        ]);
        self::assertStringNotContainsString('documents/', $response->getContent());
        self::assertStringNotContainsString(self::DISK, $response->getContent());
    }

    /** @return array{ApplyOrg, DocumentLibraryEntry} */
    private function currentEntry(string $path = 'documents/current.pdf'): array
    {
        $application = (new ApplyOrg)->forceFill([
            'application_form' => $this->source($path),
            'status' => 'Approved',
        ]);
        $application->save();
        $entry = (new DocumentLibraryEntry)->forceFill([
            'apply_org_id' => $application->id,
            'document_field' => 'application_form',
            'document_slot' => 'primary',
            'requirement_label' => 'Application Form',
            'original_filename' => 'Current Document.pdf',
            'storage_disk' => self::DISK,
            'file_path' => $path,
            'source_version' => 'version-1',
            'source_fingerprint' => str_repeat('a', 64),
        ]);
        $entry->save();

        return [$application, $entry];
    }

    private function source(string $path): string
    {
        return json_encode([
            'path' => $path,
            'name' => 'Current Document.pdf',
            'storage_disk' => self::DISK,
            'source_version' => 'version-1',
            'source_fingerprint' => str_repeat('a', 64),
        ], JSON_THROW_ON_ERROR);
    }

    private function user(string $role): User
    {
        static $id = 0;
        $id++;
        $user = (new User)->forceFill([
            'name' => "Library User {$id}",
            'email' => "library-source-{$id}@example.test",
            'password' => 'not-used',
            'role' => $role,
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
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->text('application_form')->nullable();
            $table->string('status')->nullable();
            $table->string('accreditation_year')->nullable();
            $table->timestamps();
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
        });
        Schema::create('processed_documents', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('apply_org_id');
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('storage_disk');
            $table->string('source_version')->nullable();
            $table->string('source_fingerprint', 64)->nullable();
            $table->timestamp('association_ready_at')->nullable();
            $table->string('processing_status')->default('pending');
            $table->string('output_source_version')->nullable();
            $table->timestamps();
            $table->unique(['apply_org_id', 'document_field', 'document_slot']);
        });
    }
}
