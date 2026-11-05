<?php

namespace Tests\Unit;

use App\Models\ApplyOrg;
use App\Models\DocumentLibraryEntry;
use App\Models\ProcessedDocument;
use App\Services\DocumentIntelligence\DocumentAnalysisDispatchCommand;
use App\Services\DocumentIntelligence\DocumentAnalysisRegistrar;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

/** **Validates: Document Intelligence Requirements 1.1–1.5, 8.1, 8.4, 9.2–9.4; Document Library Requirements 7.4–7.7, 8.6, 11.5** */
final class DocumentAnalysisRegistrarTest extends TestCase
{
    private DocumentAnalysisRegistrar $registrar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        $this->registrar = $this->app->make(DocumentAnalysisRegistrar::class);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('document_library_entries');
        Schema::dropIfExists('processed_documents');
        Schema::dropIfExists('apply_orgs');
        parent::tearDown();
    }

    public function test_descriptor_identity_uses_uuid_and_sha256_of_unchanged_bytes(): void
    {
        $bytes = "unchanged\0document bytes";
        $descriptor = $this->registrar->descriptorFromBytes(
            42,
            'letter_of_application',
            'primary',
            'Letter.pdf',
            'private',
            'documents/letter.pdf',
            $bytes,
        );

        self::assertTrue(Str::isUuid($descriptor->sourceVersion));
        self::assertSame(hash('sha256', $bytes), $descriptor->sourceFingerprint);
        self::assertSame("unchanged\0document bytes", $bytes);
    }

    public function test_registration_requires_the_caller_transaction(): void
    {
        $application = $this->application('documents/letter.pdf');
        $descriptor = $this->descriptor($application, 'source bytes');

        $this->expectException(LogicException::class);
        $this->registrar->register($application, [$descriptor]);
    }

    public function test_ready_source_is_registered_once_and_duplicate_registration_is_idempotent(): void
    {
        $application = $this->application('documents/letter.pdf');
        $descriptor = $this->descriptor($application, 'source bytes');

        $first = DB::transaction(fn (): array => $this->registrar->register($application, [$descriptor]));
        $analysis = ProcessedDocument::query()->sole();
        $entry = DocumentLibraryEntry::query()->sole();
        $firstCycle = $analysis->active_cycle_id;
        $entryId = $entry->id;

        self::assertSame($descriptor->sourceVersion, $entry->source_version);
        self::assertSame($descriptor->sourceFingerprint, $entry->source_fingerprint);
        self::assertSame($analysis->source_version, $entry->source_version);

        self::assertCount(1, $first);
        self::assertInstanceOf(DocumentAnalysisDispatchCommand::class, $first[0]);
        self::assertSame($analysis->id, $first[0]->analysisId);
        self::assertSame($descriptor->sourceVersion, $first[0]->sourceVersion);
        self::assertSame($firstCycle, $first[0]->cycleId);
        self::assertSame(ProcessedDocument::STATUS_PENDING, $analysis->processing_status);
        self::assertNotNull($analysis->association_ready_at);

        $duplicate = DB::transaction(fn (): array => $this->registrar->register($application, [$descriptor]));
        $unchanged = $analysis->fresh();

        self::assertSame([], $duplicate);
        self::assertSame(1, ProcessedDocument::query()->count());
        self::assertSame(1, DocumentLibraryEntry::query()->count());
        self::assertSame($entryId, DocumentLibraryEntry::query()->sole()->id);
        self::assertSame($descriptor->sourceVersion, $unchanged->source_version);
        self::assertSame($firstCycle, $unchanged->active_cycle_id);
        self::assertSame('documents/letter.pdf', $application->fresh()->letter_of_application);
        self::assertSame('under_review', $application->fresh()->status);
    }

    public function test_incomplete_association_stays_pending_and_dispatches_only_when_it_becomes_durable(): void
    {
        $application = $this->application(null);
        $descriptor = $this->descriptor($application, 'source bytes');

        $commands = DB::transaction(fn (): array => $this->registrar->register($application, [$descriptor]));
        $analysis = ProcessedDocument::query()->sole();
        $cycleId = $analysis->active_cycle_id;

        self::assertSame([], $commands);
        self::assertSame(ProcessedDocument::STATUS_PENDING, $analysis->processing_status);
        self::assertNull($analysis->association_ready_at);

        DB::transaction(function () use ($application, $descriptor, &$commands): void {
            $application->forceFill(['letter_of_application' => 'documents/letter.pdf'])->save();
            $commands = $this->registrar->register($application, [$descriptor]);
        });

        self::assertCount(1, $commands);
        self::assertSame($cycleId, $commands[0]->cycleId);
        self::assertNotNull($analysis->fresh()->association_ready_at);
    }

    public function test_replacement_gets_a_fresh_cycle_and_withholds_all_prior_output(): void
    {
        $application = $this->application('documents/letter.pdf');
        $original = $this->descriptor($application, 'old bytes');
        DB::transaction(fn (): array => $this->registrar->register($application, [$original]));
        $analysis = ProcessedDocument::query()->sole();
        $entryId = DocumentLibraryEntry::query()->sole()->id;
        $oldCycle = $analysis->active_cycle_id;
        $analysis->forceFill([
            'processing_status' => ProcessedDocument::STATUS_COMPLETED,
            'active_cycle_id' => null,
            'cycle_kind' => null,
            'document_type' => 'letter',
            'document_type_name' => 'Letter',
            'summary' => 'Old output',
            'tags' => ['old'],
            'key_information' => ['year' => '2025'],
            'extracted_text' => 'old text',
            'processed_at' => now(),
            'output_source_version' => $original->sourceVersion,
            'summary_version' => 2,
            'summary_processed_at' => now(),
        ])->save();

        $replacement = $this->descriptor($application, 'replacement bytes');
        $commands = DB::transaction(fn (): array => $this->registrar->register($application, [$replacement]));
        $fresh = $analysis->fresh();
        $currentEntry = DocumentLibraryEntry::query()->sole();

        self::assertCount(1, $commands);
        self::assertSame($entryId, $currentEntry->id);
        self::assertSame($replacement->sourceVersion, $currentEntry->source_version);
        self::assertSame($replacement->sourceFingerprint, $currentEntry->source_fingerprint);
        self::assertSame($fresh->source_version, $currentEntry->source_version);
        self::assertNull($fresh->output_source_version);
        self::assertNotSame($original->sourceVersion, $replacement->sourceVersion);
        self::assertNotSame($oldCycle, $fresh->active_cycle_id);
        self::assertSame($replacement->sourceVersion, $fresh->source_version);
        self::assertSame(hash('sha256', 'replacement bytes'), $fresh->source_fingerprint);
        self::assertSame(ProcessedDocument::STATUS_PENDING, $fresh->processing_status);
        self::assertNull($fresh->output_source_version);
        self::assertNull($fresh->processed_at);
        self::assertNull($fresh->summary);
        self::assertNull($fresh->tags);
        self::assertNull($fresh->document_type);
        self::assertFalse($fresh->hasCurrentOutput());
        self::assertSame('documents/letter.pdf', $application->fresh()->letter_of_application);
        self::assertSame('under_review', $application->fresh()->status);
    }

    public function test_source_removal_deletes_only_the_library_projection(): void
    {
        $application = $this->application('documents/letter.pdf');
        $descriptor = $this->descriptor($application, 'source bytes');
        DB::transaction(fn (): array => $this->registrar->register($application, [$descriptor]));
        $analysisId = ProcessedDocument::query()->sole()->id;

        DB::transaction(function () use ($application): void {
            $application->forceFill(['letter_of_application' => null])->save();
            $this->registrar->register($application, []);
        });

        self::assertSame(0, DocumentLibraryEntry::query()->count());
        self::assertSame($analysisId, ProcessedDocument::query()->sole()->id);
        self::assertNull($application->fresh()->letter_of_application);
        self::assertSame('under_review', $application->fresh()->status);
    }

    private function application(?string $path): ApplyOrg
    {
        $id = DB::table('apply_orgs')->insertGetId([
            'letter_of_application' => $path,
            'status' => 'under_review',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApplyOrg::query()->findOrFail($id);
    }

    private function descriptor(ApplyOrg $application, string $bytes): StoredDocumentDescriptor
    {
        return $this->registrar->descriptorFromBytes(
            (int) $application->id,
            'letter_of_application',
            'primary',
            'Letter.pdf',
            'private',
            'documents/letter.pdf',
            $bytes,
        );
    }

    private function createSchema(): void
    {
        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('letter_of_application')->nullable();
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
