<?php

namespace Database\Factories;

use App\Models\ProcessedDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProcessedDocument> */
class ProcessedDocumentFactory extends Factory
{
    protected $model = ProcessedDocument::class;

    public function definition(): array
    {
        $sourceVersion = (string) Str::uuid();

        return [
            'apply_org_id' => fake()->numberBetween(1, 1000),
            'document_field' => 'letter_of_application',
            'document_slot' => 'primary',
            'original_filename' => fake()->word().'.pdf',
            'file_path' => 'applications/'.Str::uuid().'.pdf',
            'storage_disk' => 'r2',
            'source_version' => $sourceVersion,
            'source_fingerprint' => hash('sha256', fake()->uuid()),
            'association_ready_at' => now(),
            'active_cycle_id' => (string) Str::uuid(),
            'cycle_kind' => 'initial',
            'cycle_attempt_count' => 0,
            'processing_status' => ProcessedDocument::STATUS_PENDING,
            'summary_version' => 0,
            'resummarization_status' => 'idle',
        ];
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'processing_status' => ProcessedDocument::STATUS_COMPLETED,
                'output_source_version' => $attributes['source_version'],
                'summary' => fake()->sentence(),
                'tags' => [fake()->word()],
                'key_information' => [],
                'extracted_text' => fake()->paragraph(),
                'processed_at' => now(),
                'summary_version' => 1,
                'summary_processed_at' => now(),
            ];
        });
    }
}
