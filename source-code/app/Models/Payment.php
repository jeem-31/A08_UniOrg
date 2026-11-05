<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'user_id',
        'title',
        'description',
        'due_date',
        'is_announcement',
        'gcash_number',
        'payment_instructions',
        'transaction_id',
        'category',
        'amount',
        'qr_code',
        'is_required_membership_fee',
        'membership_cycle_start',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'datetime',
            'paid_at' => 'datetime',
            'is_required_membership_fee' => \App\Casts\PostgresBoolean::class,
            'membership_cycle_start' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isProtectedMembershipFee(): bool
    {
        return $this->is_required_membership_fee === true;
    }

    public function scopeRequiredMembershipFee($query)
    {
        return $query->whereRaw($query->getModel()->getTable().'.is_required_membership_fee = TRUE');
    }


}
