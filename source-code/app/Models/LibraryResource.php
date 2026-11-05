<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryResource extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'user_id',
        'title',
        'description',
        'file_path',
        'url',
        'file_type',
        'resource_type',
        'category',
        'download_count',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'download_count' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePublished($query)
    {
        return $query->whereRaw('CAST(is_published AS INTEGER) = ?', [1]);
    }

    public function incrementDownloadCount()
    {
        $this->increment('download_count');
    }
}
