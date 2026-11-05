<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'organization_id',
        'user_id',
        'student_id',
        'name',
        'position',
        'year_level',
        'photo',
        'vote_count',
    ];

    protected function casts(): array
    {
        return [
            'vote_count' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function election()
    {
        return $this->belongsTo(Election::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * Get the organization through the election relationship
     */
    public function organization()
    {
        return $this->hasOneThrough(
            Organization::class,
            Election::class,
            'id', // Foreign key on elections table
            'id', // Foreign key on organizations table
            'election_id', // Local key on candidates table
            'organization_id' // Local key on elections table
        );
    }
}
