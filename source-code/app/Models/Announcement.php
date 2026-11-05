<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'user_id',
        'title',
        'content',
        'type',
        'visibility',
        'images',
        'is_published',
        'published_at',
        'scheduled_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'images' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function targetOrganizations()
    {
        return $this->belongsToMany(Organization::class, 'announcement_organization');
    }

    public function scopePublished($query)
    {
        return $query->whereRaw('CAST(is_published AS INTEGER) = ?', [1])
            ->where('status', '!=', 'hidden');
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
