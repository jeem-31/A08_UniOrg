<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentAnnouncement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'category',
        'amount',
        'due_date',
        'status',
        'allow_gcash',
        'allow_manual',
        'gcash_number',
        'payment_instructions',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'allow_gcash' => 'boolean',
            'allow_manual' => 'boolean',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'announcement_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function getCategoryLabelAttribute()
    {
        return match($this->category) {
            'tour_fee' => 'Tour Fee',
            'membership_fee' => 'Membership Fee',
            'event_fee' => 'Event Fee',
            'document_fee' => 'Document Fee',
            'medical_fee' => 'Medical Fee',
            default => ucfirst(str_replace('_', ' ', $this->category)),
        };
    }
}
