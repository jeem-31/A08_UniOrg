<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EdushareItem extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'user_id',
        'folder_id',
        'title',
        'description',
        'file_path',
        'file_type',
        'category',
        'course',
        'download_count',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'download_count' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function folder()
    {
        return $this->belongsTo(EdushareFolder::class, 'folder_id');
    }

    public function shares()
    {
        return $this->hasMany(EdushareShare::class, 'item_id');
    }

    public function scopePublic($query)
    {
        return $query->whereRaw('CAST(is_public AS INTEGER) = ?', [1]);
    }

    public function incrementDownloadCount()
    {
        $this->increment('download_count');
    }
}
