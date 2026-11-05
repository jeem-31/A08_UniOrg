<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentProcessingAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'processed_document_id',
        'cycle_id',
        'source_version',
        'operation',
        'ordinal',
        'outcome',
        'failure_class',
        'provider_status_code',
        'sanitized_message',
        'started_at',
        'finished_at',
        'duration_ms',
    ];

    protected $hidden = [
        'cycle_id',
        'source_version',
        'failure_class',
        'provider_status_code',
        'sanitized_message',
    ];

    protected function casts(): array
    {
        return [
            'ordinal' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
        ];
    }

    public function processedDocument(): BelongsTo
    {
        return $this->belongsTo(ProcessedDocument::class);
    }
}
