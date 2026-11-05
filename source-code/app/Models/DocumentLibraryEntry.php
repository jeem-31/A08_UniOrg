<?php

namespace App\Models;

use App\Services\DocumentLibrary\CurrentLibraryOutputScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLibraryEntry extends Model
{
    use HasFactory;

    /**
     * Projection rows may only be maintained by the document library indexer.
     */
    protected $guarded = ['*'];

    /**
     * Source identity is available only to explicit authorization/query code.
     */
    protected $hidden = [
        'storage_disk',
        'file_path',
        'source_version',
        'source_fingerprint',
    ];

    public function applyOrg(): BelongsTo
    {
        return $this->belongsTo(ApplyOrg::class, 'apply_org_id');
    }

    public function scopeWithCurrentLibraryOutput(
        Builder $query,
        string $entryAlias = 'document_library_entries',
    ): Builder {
        return (new CurrentLibraryOutputScope)->apply($query, $entryAlias);
    }
}
