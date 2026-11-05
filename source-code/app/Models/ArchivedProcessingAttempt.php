<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivedProcessingAttempt extends Model
{
    use HasFactory;

    protected $table = 'archived_processing_attempts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function archivedProcessedDocument(): BelongsTo
    {
        return $this->belongsTo(ArchivedProcessedDocument::class, 'archived_processed_document_id');
    }
}
