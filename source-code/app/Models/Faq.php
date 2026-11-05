<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'question',
        'answer',
        'category',
        'keywords',
        'view_count',
        'is_published',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'view_count' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished($query)
    {
        return $query->whereRaw('CAST(is_published AS INTEGER) = ?', [1]);
    }

    public function incrementViewCount()
    {
        $this->increment('view_count');
    }
}
