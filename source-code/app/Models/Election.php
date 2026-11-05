<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Casts\PostgresBoolean;

class Election extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'is_active',
        'is_posted',
        'start_notified',
        'results_published',
        'secretary_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_active' => PostgresBoolean::class,
            'is_posted' => PostgresBoolean::class,
            'start_notified' => PostgresBoolean::class,
            'results_published' => PostgresBoolean::class,
        ];
    }

    public function secretary()
    {
        return $this->belongsTo(Candidate::class, 'secretary_id');
    }

    public function candidates()
    {
        return $this->hasMany(Candidate::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    public function officerTransition()
    {
        return $this->hasOne(ElectionOfficerTransition::class);
    }

    public function scopeActive($query)
    {
        return $query->whereRaw('CAST(is_active AS INTEGER) = ?', [1])
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }
}
