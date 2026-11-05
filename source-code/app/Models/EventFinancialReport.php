<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventFinancialReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'submitted_by',
        'total_expenses',
        'narrative_report',
        'expense_receipts',
        'approved_letter',
        'status',
        'remarks',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_expenses' => 'decimal:2',
            'expense_receipts' => 'array',
            'reviewed_at' => 'datetime',
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
}
