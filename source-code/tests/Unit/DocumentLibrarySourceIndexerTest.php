<?php

namespace Tests\Unit;

use App\Models\ApplyOrg;
use App\Services\DocumentLibrary\DocumentLibrarySourceIndexer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** **Validates: Requirements 1.3, 4.9, 7.6, 7.7, 8.5, 8.7, 8.8** */
final class DocumentLibrarySourceIndexerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('document_library_entries');
        Schema::dropIfExists('processed_documents');
        Schema::dropIfExists('apply_orgs');
        parent::tearDown();
    }

    public function test_it_reconciles_catalog_sources_by_logical_slot_without_mutating_source_or_review_state(): void
    {
        $application = $this->application([
            'status' => 'Approved',
            'application_form' => json_encode([
                'path' => 'applications/form-v1.pdf',
                'name' => 'Application.pdf',
                'storage_disk' => 'private',
                'source_version' => '11111111-1111-4111-8111-111111111111',
                'source_fingerprint' => str_repeat('a', 64),
            ], JSON_THROW_ON_ERROR),
            'list_of_members' => json_encode([
                $this->member('member-one', 'members/one.pdf', 'One.pdf'),
                $this->member('member-two', 'members/two.pdf', 'Two.pdf'),
            ], JSON_THROW_ON_ERROR),
            'renewal_form' => 'unsupported/ignored.pdf',
        ]);
        DB::table('processed_documents')->insert([
            'apply_org_id' => $application->id,
            'document_field' => 'application_form',
            'document_slot' => 'primary',
            'processing_status' => 'completed',
            'summary' => 'Persisted generated summary',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $applicationBefore = DB::table('apply_orgs')->where('id', $application->id)->first();
        $analysisBefore = DB::table('processed_documents')->where('apply_org_id', $application->id)->first();

        $indexer = app(DocumentLibrarySourceIndexer::class);
        self::assertSame(3, $indexer->syncApplication($application));

        $entries = DB::table('document_library_entries')
            ->where('apply_org_id', $application->id)
            ->orderBy('document_field')
            ->orderBy('document_slot')
            ->get();
        self::assertCount(3, $entries);
        self::assertSame(
            ['application_form:primary', 'list_of_members:member-one', 'list_of_members:member-two'],
            $entries->map(fn ($entry): string => "{$entry->document_field}:{$entry->document_slot}")->all(),
        );
        self::assertSame('Application Form', $entries[0]->requirement_label);
        self::assertSame('private', $entries[0]->storage_disk);
        self::assertEquals($applicationBefore, DB::table('apply_orgs')->where('id', $application->id)->first());
        self::assertEquals($analysisBefore, DB::table('processed_documents')->where('apply_org_id', $application->id)->first());

        $originalId = $entries[0]->id;
        $application->forceFill([
            'application_form' => json_encode([
                'path' => 'applications/form-v2.pdf',
                'name' => 'Application replacement.pdf',
                'storage_disk' => 'private',
                'source_version' => '22222222-2222-4222-8222-222222222222',
                'source_fingerprint' => str_repeat('d', 64),
            ], JSON_THROW_ON_ERROR),
            'list_of_members' => json_encode([
                $this->member('member-one', 'members/one.pdf', 'One.pdf'),
            ], JSON_THROW_ON_ERROR),
        ])->save();
        $applicationBeforeRepair = DB::table('apply_orgs')->where('id', $application->id)->first();

        self::assertSame(2, $indexer->syncApplication($application->fresh()));
        $repaired = DB::table('document_library_entries')
            ->where('apply_org_id', $application->id)
            ->orderBy('id')
            ->get();

        self::assertCount(2, $repaired);
        self::assertSame($originalId, $repaired->firstWhere('document_field', 'application_form')->id);
        self::assertSame('applications/form-v2.pdf', $repaired->firstWhere('document_field', 'application_form')->file_path);
        self::assertSame('22222222-2222-4222-8222-222222222222', $repaired->firstWhere('document_field', 'application_form')->source_version);
        self::assertNull($repaired->firstWhere('document_slot', 'member-two'));
        self::assertEquals($applicationBeforeRepair, DB::table('apply_orgs')->where('id', $application->id)->first());
        self::assertEquals($analysisBefore, DB::table('processed_documents')->where('apply_org_id', $application->id)->first());

        $ids = $repaired->pluck('id')->all();
        self::assertSame(2, $indexer->syncApplication($application->fresh()));
        self::assertSame($ids, DB::table('document_library_entries')->orderBy('id')->pluck('id')->all());
    }

    public function test_backfill_command_indexes_every_application_and_is_safe_to_repeat(): void
    {
        $this->application([
            'status' => 'Pending',
            'application_form' => 'applications/first.pdf',
        ]);
        $this->application([
            'status' => 'Rejected',
            'list_of_members' => json_encode([
                $this->member('member-rejected', 'members/rejected.pdf', 'Rejected Members.pdf'),
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->artisan('document-library:backfill')
            ->expectsOutput('Indexed 2 document(s) from 2 application(s).')
            ->assertSuccessful();
        $ids = DB::table('document_library_entries')->orderBy('id')->pluck('id')->all();

        $this->artisan('document-library:backfill')
            ->expectsOutput('Indexed 2 document(s) from 2 application(s).')
            ->assertSuccessful();

        self::assertCount(2, $ids);
        self::assertSame($ids, DB::table('document_library_entries')->orderBy('id')->pluck('id')->all());
        self::assertSame(['Pending', 'Rejected'], DB::table('apply_orgs')->orderBy('id')->pluck('status')->all());
    }

    /** @param array<string, mixed> $attributes */
    private function application(array $attributes): ApplyOrg
    {
        $application = (new ApplyOrg)->forceFill(array_merge([
            'organization_name' => 'Test Organization',
            'status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
        $application->save();

        return $application;
    }

    /** @return array<string, string> */
    private function member(string $slot, string $path, string $name): array
    {
        return [
            'slot' => $slot,
            'path' => $path,
            'name' => $name,
            'storage_disk' => 'private',
            'source_version' => $slot.'-version',
            'source_fingerprint' => hash('sha256', $slot),
        ];
    }

    private function createSchema(): void
    {
        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('organization_name')->nullable();
            $table->string('status')->nullable();
            $table->text('application_form')->nullable();
            $table->text('list_of_members')->nullable();
            $table->text('renewal_form')->nullable();
            $table->timestamps();
        });
        Schema::create('processed_documents', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('apply_org_id');
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('processing_status');
            $table->text('summary')->nullable();
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
            $table->unique(['apply_org_id', 'document_field', 'document_slot']);
        });
    }
}
