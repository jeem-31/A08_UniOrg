<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchivedProcessedDocument extends Model
{
    use HasFactory;

    protected $table = 'archived_processed_documents';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'key_information' => 'array',
            'confidence_score' => 'decimal:2',
            'processed_at' => 'datetime',
            'association_ready_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'summary_processed_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function archive(): BelongsTo
    {
        return $this->belongsTo(ApplyOrgDocumentArchive::class, 'archive_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ArchivedProcessingAttempt::class, 'archived_processed_document_id');
    }
}
