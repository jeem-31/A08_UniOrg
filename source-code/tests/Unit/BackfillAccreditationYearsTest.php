<?php

namespace Tests\Unit;

use App\Models\ApplyOrg;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** **Validates: Requirements 2.5, 3.1, 3.2** */
final class BackfillAccreditationYearsTest extends TestCase
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

    public function test_it_backfills_the_majority_current_school_year_for_approved_applications(): void
    {
        $applicationId = $this->application('Approved');
        $this->currentPair($applicationId, 'application_form', 'primary', '2025-2026', 'aa');
        $this->currentPair($applicationId, 'plan_of_activities', 'primary', '2025-2026', 'bb');
        $this->currentPair($applicationId, 'list_of_members', 'primary', '2024-2025', 'cc');

        $this->artisan('library:backfill-accreditation-years')
            ->expectsOutput('Backfilled accreditation years for 1 application(s); 0 left without a determinable year.')
            ->assertSuccessful();

        self::assertSame(
            '2025-2026',
            DB::table('apply_orgs')->where('id', $applicationId)->value('accreditation_year'),
        );
    }

    public function test_it_leaves_applications_without_a_determinable_year_untouched(): void
    {
        $noYear = $this->application('Approved');
        $this->currentPair($noYear, 'application_form', 'primary', null, 'aa');

        $pending = $this->application('Pending');
        $this->currentPair($pending, 'application_form', 'primary', '2026-2027', 'bb');

        $alreadySet = $this->application('Approved');
        DB::table('apply_orgs')->where('id', $alreadySet)->update(['accreditation_year' => '2023-2024']);
        $this->currentPair($alreadySet, 'application_form', 'primary', '2022-2023', 'cc');

        $this->artisan('library:backfill-accreditation-years')
            ->expectsOutput('Backfilled accreditation years for 0 application(s); 1 left without a determinable year.')
            ->assertSuccessful();

        self::assertNull(DB::table('apply_orgs')->where('id', $noYear)->value('accreditation_year'));
        self::assertNull(DB::table('apply_orgs')->where('id', $pending)->value('accreditation_year'));
        self::assertSame(
            '2023-2024',
            DB::table('apply_orgs')->where('id', $alreadySet)->value('accreditation_year'),
        );
    }

    private function application(string $status): int
    {
        return DB::table('apply_orgs')->insertGetId([
            'organization_name' => 'Test Organization',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function currentPair(
        int $applicationId,
        string $field,
        string $slot,
        ?string $schoolYear,
        string $fingerprintSeed,
    ): void {
        $fingerprint = str_repeat($fingerprintSeed, 64);
        DB::table('document_library_entries')->insert([
            'apply_org_id' => $applicationId,
            'document_field' => $field,
            'document_slot' => $slot,
            'requirement_label' => 'Application Form',
            'original_filename' => "{$field}.pdf",
            'storage_disk' => 'private',
            'file_path' => "documents/{$applicationId}-{$field}.pdf",
            'source_version' => 'v1',
            'source_fingerprint' => $fingerprint,
        ]);
        DB::table('processed_documents')->insert([
            'apply_org_id' => $applicationId,
            'document_field' => $field,
            'document_slot' => $slot,
            'storage_disk' => 'private',
            'file_path' => "documents/{$applicationId}-{$field}.pdf",
            'source_version' => 'v1',
            'source_fingerprint' => $fingerprint,
            'association_ready_at' => now(),
            'output_source_version' => 'v1',
            'processing_status' => 'completed',
            'key_information' => json_encode(
                $schoolYear === null ? [] : ['school_year' => $schoolYear],
                JSON_THROW_ON_ERROR,
            ),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('organization_name')->nullable();
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
            $table->unique(['apply_org_id', 'document_field', 'document_slot']);
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
            $table->text('tags')->nullable();
            $table->text('key_information')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->unique(['apply_org_id', 'document_field', 'document_slot']);
        });
    }
}
