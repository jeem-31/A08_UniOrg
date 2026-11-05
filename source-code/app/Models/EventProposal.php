<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'organization_id',
        'submitted_by',
        'request_letter',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'osas_rejection_reason',
        'osas_reviewed_by',
        'osas_reviewed_at',
        'reschedule_status',
        'reschedule_reason',
        'reschedule_requested_by',
        'reschedule_requested_at',
        'reschedule_reviewed_by',
        'reschedule_reviewed_at',
        'reschedule_rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'osas_reviewed_at' => 'datetime',
            'reschedule_requested_at' => 'datetime',
            'reschedule_reviewed_at' => 'datetime',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function osasReviewer()
    {
        return $this->belongsTo(User::class, 'osas_reviewed_by');
    }

    public function rescheduleRequester()
    {
        return $this->belongsTo(User::class, 'reschedule_requested_by');
    }

    public function rescheduleReviewer()
    {
        return $this->belongsTo(User::class, 'reschedule_reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
