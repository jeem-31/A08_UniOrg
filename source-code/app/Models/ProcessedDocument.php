<?php

namespace App\Models;

use App\Casts\PostgresBoolean;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessedDocument extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * Analysis records may only be populated through registration/lifecycle services.
     */
    protected $guarded = ['*'];

    /**
     * Protected source, generated output, concurrency, and failure data is opt-in.
     */
    protected $hidden = [
        'file_path',
        'storage_disk',
        'source_version',
        'source_fingerprint',
        'association_ready_at',
        'active_cycle_id',
        'cycle_kind',
        'cycle_attempt_count',
        'processing_started_at',
        'processing_duration_ms',
        'output_source_version',
        'failure_code',
        'error_message',
        'document_type',
        'document_type_name',
        'summary',
        'tags',
        'key_information',
        'extracted_text',
        'confidence_score',
        'summary_version',
        'summary_processed_at',
        'resummarization_status',
        'resummarization_cycle_id',
        'resummarization_error_message',
        'last_resummarization_changed',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'key_information' => 'array',
            'confidence_score' => 'decimal:2',
            'association_ready_at' => 'datetime',
            'cycle_attempt_count' => 'integer',
            'processing_started_at' => 'datetime',
            'processing_duration_ms' => 'integer',
            'processed_at' => 'datetime',
            'summary_version' => 'integer',
            'summary_processed_at' => 'datetime',
            'last_resummarization_changed' => PostgresBoolean::class,
        ];
    }

    public function applyOrg(): BelongsTo
    {
        return $this->belongsTo(ApplyOrg::class, 'apply_org_id');
    }

    public function processingAttempts(): HasMany
    {
        return $this->hasMany(DocumentProcessingAttempt::class, 'processed_document_id');
    }

    public function scopeWithCurrentOutput(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('processing_status'), self::STATUS_COMPLETED)
            ->whereNotNull($this->qualifyColumn('output_source_version'))
            ->whereColumn(
                $this->qualifyColumn('output_source_version'),
                $this->qualifyColumn('source_version')
            );
    }

    public function hasCurrentOutput(): bool
    {
        return $this->processing_status === self::STATUS_COMPLETED
            && $this->output_source_version !== null
            && hash_equals((string) $this->source_version, (string) $this->output_source_version);
    }

    /**
     * Return generated fields only after proving that they belong to the current source.
     * Authorization must be enforced by the caller before using this helper.
     *
     * @return array<string, mixed>|null
     */
    public function outputForCurrentSource(): ?array
    {
        if (! $this->hasCurrentOutput()) {
            return null;
        }

        return [
            'document_type' => $this->document_type,
            'document_type_name' => $this->document_type_name,
            'summary' => $this->summary,
            'tags' => $this->tags,
            'key_information' => $this->key_information,
            'extracted_text' => $this->extracted_text,
            'confidence_score' => $this->confidence_score,
        ];
    }

    public function isProcessed(): bool
    {
        return $this->processing_status === self::STATUS_COMPLETED;
    }

    public function hasFailed(): bool
    {
        return $this->processing_status === self::STATUS_FAILED;
    }

    public function getConfidenceLevelAttribute(): string
    {
        if ($this->confidence_score >= 80) {
            return 'High';
        }

        if ($this->confidence_score >= 50) {
            return 'Medium';
        }

        return 'Low';
    }

    public function getConfidenceBadgeColorAttribute(): string
    {
        if ($this->confidence_score >= 80) {
            return 'green';
        }

        if ($this->confidence_score >= 50) {
            return 'yellow';
        }

        return 'red';
    }
}
