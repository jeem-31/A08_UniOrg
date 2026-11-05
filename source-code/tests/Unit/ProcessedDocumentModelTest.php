<?php

namespace Tests\Unit;

use App\Models\ApplyOrg;
use App\Models\DocumentProcessingAttempt;
use App\Models\ProcessedDocument;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** **Validates: Requirements 2.1, 2.6, 7.5, 9.1** */
class ProcessedDocumentModelTest extends TestCase
{
    public function test_lifecycle_and_source_fields_cannot_be_mass_assigned(): void
    {
        $this->expectException(MassAssignmentException::class);

        new ProcessedDocument([
            'apply_org_id' => 10,
            'source_version' => 'source-2',
            'processing_status' => ProcessedDocument::STATUS_COMPLETED,
            'summary' => 'Untrusted replacement',
            'error_message' => 'Untrusted error',
        ]);
    }

    public function test_lifecycle_metadata_is_cast_to_domain_types(): void
    {
        $analysis = (new ProcessedDocument)->forceFill([
            'tags' => ['policy'],
            'key_information' => ['year' => '2026'],
            'association_ready_at' => '2026-01-01 10:00:00',
            'cycle_attempt_count' => '2',
            'processing_duration_ms' => '1250',
            'summary_version' => '3',
            'last_resummarization_changed' => 1,
        ]);

        $this->assertSame(['policy'], $analysis->tags);
        $this->assertSame(['year' => '2026'], $analysis->key_information);
        $this->assertInstanceOf(Carbon::class, $analysis->association_ready_at);
        $this->assertSame(2, $analysis->cycle_attempt_count);
        $this->assertSame(1250, $analysis->processing_duration_ms);
        $this->assertSame(3, $analysis->summary_version);
        $this->assertTrue($analysis->last_resummarization_changed);
    }

    public function test_default_serialization_omits_source_output_and_failure_data(): void
    {
        $analysis = (new ProcessedDocument)->forceFill([
            'id' => 5,
            'document_field' => 'constitution_bylaws',
            'processing_status' => ProcessedDocument::STATUS_FAILED,
            'file_path' => 'private/source.pdf',
            'source_version' => 'source-1',
            'summary' => 'Protected output',
            'tags' => ['constitution'],
            'error_message' => 'Safe for an authorized reviewer only',
        ]);

        $serialized = $analysis->toArray();

        $this->assertSame(5, $serialized['id']);
        $this->assertSame(ProcessedDocument::STATUS_FAILED, $serialized['processing_status']);
        $this->assertArrayNotHasKey('file_path', $serialized);
        $this->assertArrayNotHasKey('source_version', $serialized);
        $this->assertArrayNotHasKey('summary', $serialized);
        $this->assertArrayNotHasKey('tags', $serialized);
        $this->assertArrayNotHasKey('error_message', $serialized);
    }

    public function test_output_helper_rejects_incomplete_and_stale_output(): void
    {
        $analysis = (new ProcessedDocument)->forceFill([
            'processing_status' => ProcessedDocument::STATUS_PROCESSING,
            'source_version' => 'source-2',
            'output_source_version' => 'source-1',
            'summary' => 'Stale summary',
        ]);

        $this->assertFalse($analysis->hasCurrentOutput());
        $this->assertNull($analysis->outputForCurrentSource());

        $analysis->forceFill([
            'processing_status' => ProcessedDocument::STATUS_COMPLETED,
            'output_source_version' => 'source-2',
            'document_type' => 'constitution',
            'tags' => ['constitution'],
            'key_information' => ['year' => '2026'],
        ]);

        $this->assertTrue($analysis->hasCurrentOutput());
        $this->assertSame('Stale summary', $analysis->outputForCurrentSource()['summary']);
        $this->assertSame(['constitution'], $analysis->outputForCurrentSource()['tags']);
    }

    public function test_current_output_scope_requires_completed_matching_source(): void
    {
        $query = ProcessedDocument::query()->withCurrentOutput();

        $this->assertStringContainsString('processing_status', $query->toSql());
        $this->assertStringContainsString('output_source_version', $query->toSql());
        $this->assertStringContainsString('source_version', $query->toSql());
        $this->assertSame([ProcessedDocument::STATUS_COMPLETED], $query->getBindings());
    }

    public function test_analysis_relationships_use_explicit_foreign_keys(): void
    {
        $applicationRelation = (new ApplyOrg)->processedDocuments();
        $attemptRelation = (new ProcessedDocument)->processingAttempts();
        $analysisRelation = (new DocumentProcessingAttempt)->processedDocument();

        $this->assertSame('apply_org_id', $applicationRelation->getForeignKeyName());
        $this->assertSame('processed_document_id', $attemptRelation->getForeignKeyName());
        $this->assertSame('processed_document_id', $analysisRelation->getForeignKeyName());
    }

    public function test_factories_create_casted_model_instances_without_persisting(): void
    {
        $analysis = ProcessedDocument::factory()->completed()->make();
        $attempt = DocumentProcessingAttempt::factory()->make([
            'processed_document_id' => 1,
            'ordinal' => '2',
            'duration_ms' => '25',
        ]);

        $this->assertTrue($analysis->hasCurrentOutput());
        $this->assertIsArray($analysis->tags);
        $this->assertSame(2, $attempt->ordinal);
        $this->assertSame(25, $attempt->duration_ms);
        $this->assertArrayNotHasKey('sanitized_message', $attempt->toArray());
    }
}
