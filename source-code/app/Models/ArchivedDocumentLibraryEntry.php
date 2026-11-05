<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivedDocumentLibraryEntry extends Model
{
    use HasFactory;

    protected $table = 'archived_document_library_entries';

    protected $guarded = [];

    public function archive(): BelongsTo
    {
        return $this->belongsTo(ApplyOrgDocumentArchive::class, 'archive_id');
    }
}
