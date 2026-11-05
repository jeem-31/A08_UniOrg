<?php

namespace Database\Factories;

use App\Models\DocumentProcessingAttempt;
use App\Models\ProcessedDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DocumentProcessingAttempt> */
class DocumentProcessingAttemptFactory extends Factory
{
    protected $model = DocumentProcessingAttempt::class;

    public function definition(): array
    {
        return [
            'processed_document_id' => ProcessedDocument::factory(),
            'cycle_id' => (string) Str::uuid(),
            'source_version' => (string) Str::uuid(),
            'operation' => 'initial',
            'ordinal' => 1,
            'outcome' => 'running',
            'failure_class' => null,
            'provider_status_code' => null,
            'sanitized_message' => null,
            'started_at' => now(),
            'finished_at' => null,
            'duration_ms' => null,
        ];
    }
}
