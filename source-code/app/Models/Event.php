<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory, BelongsToOrganization;

    public const VENUES = [
        'Activity Center (AC)',
        'Basketball Court',
        'Volleyball Court',
        'Quadrangle',
        'CIHTM Hall',
        'Field',
        'Outside of School',
    ];

    /**
     * Department-owned premises: venue => owning department acronym.
     * Only these venues trigger a dean FYI notice on propose + status changes.
     */
    public const VENUE_OWNER_MAP = [
        'Quadrangle' => 'CCS',
        'CIHTM Hall' => 'CIHTM',
    ];

    /**
     * Resolve the owning department acronym for a venue (case-insensitive).
     * Returns null when the venue is not a department-owned premise.
     */
    public static function venueOwnerAcronym(?string $venue): ?string
    {
        $venue = trim((string) $venue);
        if ($venue === '') {
            return null;
        }
        foreach (self::VENUE_OWNER_MAP as $ownedVenue => $acronym) {
            if (strcasecmp($venue, $ownedVenue) === 0) {
                return $acronym;
            }
        }
        return null;
    }

    public const CATEGORIES = [
        'general_on' => 'General Activities (On Campus)',
        'general_off' => 'General Activities (Off Campus)',
        'outreach_on' => 'Outreach Program (On Campus)',
        'outreach_off' => 'Outreach Program (Off Campus)',
    ];

    protected $appends = ['category_label'];

    protected $fillable = [
        'organization_id',
        'user_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'venue',
        'category',
        'planned_budget',
        'images',
        'is_published',
        'status',
        'approval_status',
        'approved_by',
        'approved_at',
        'dean_approved_at',
        'osas_status',
        'osas_approved_by',
        'osas_approved_at',
        'hardcopy_received_at',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_published' => 'boolean',
            'scheduled_at' => 'datetime',
            'approved_at' => 'datetime',
            'dean_approved_at' => 'datetime',
            'osas_approved_at' => 'datetime',
            'hardcopy_received_at' => 'datetime',
            'planned_budget' => 'decimal:2',
            'images' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function osasApprover()
    {
        return $this->belongsTo(User::class, 'osas_approved_by');
    }

    public function proposal()
    {
        return $this->hasOne(EventProposal::class);
    }

    public function financialReport()
    {
        return $this->hasOne(EventFinancialReport::class);
    }

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ($this->category ?: '—');
    }

    public function scopePublished($query)
    {
        return $query->whereRaw('CAST(is_published AS INTEGER) = ?', [1])->where('status', '!=', 'hidden');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>=', now());
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
