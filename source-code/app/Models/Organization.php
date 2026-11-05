<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'org_acronym',
        'type',
        'department_id',
        'status',
        'accreditation_started_at',
        'accreditation_expires_at',
        'archived_at',
        'has_system',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'type' => 'string',
            'has_system' => 'boolean',
            'accreditation_started_at' => 'datetime',
            'accreditation_expires_at' => 'datetime',
            'archived_at' => 'datetime',
            'management_version' => 'integer',
        ];
    }

    /**
     * Check if the organization is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === 'Archived' || ! is_null($this->archived_at);
    }

    /**
     * Check if the organization has a pending renewal (auto-generated or submitted).
     * Includes both Pending (auto queue) and For-Checking (submitted, awaiting OSAS review)
     * so Start Renewal is idempotent — 1 renew only until approved/rejected (dun lang ako approve).
     */
    public function hasPendingRenewal(): bool
    {
        return \Illuminate\Support\Facades\DB::table('apply_orgs')
            ->where('organization_id', $this->id)
            ->where('application_type', 'renew')
            ->whereIn('status', ['Pending', 'For-Checking'])
            ->exists();
    }

    /**
     * Check if the accreditation is expired.
     * For strict B: an org with pending renewal is considered expired until approved.
     */
    public function isExpired(): bool
    {
        if ($this->hasPendingRenewal()) {
            return true;
        }

        if (is_null($this->accreditation_expires_at)) {
            return false;
        }

        return $this->accreditation_expires_at->isPast();
    }

    /**
     * Number of whole days until the accreditation expires. Negative when the
     * period has already ended, null when no expiration date is set.
     */
    public function daysUntilExpiration(): ?int
    {
        if (is_null($this->accreditation_expires_at)) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->accreditation_expires_at->copy()->startOfDay(), false);
    }

    /**
     * Accreditation status used by dashboards and widgets:
     * `none` (no period), `expired`, `expiring` (within 30 days), or `active`.
     * Strict B: pending renewal forces expired until approved.
     */
    public function accreditationStatus(): string
    {
        if ($this->hasPendingRenewal()) {
            return 'expired';
        }

        if (is_null($this->accreditation_expires_at)) {
            return 'none';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        $days = $this->daysUntilExpiration();

        return $days !== null && $days <= 30 ? 'expiring' : 'active';
    }

    /**
     * Check if the organization can be used by officers and students.
     */
    public function canBeUsed(): bool
    {
        return $this->status === 'Approved' && ! $this->isArchived() && ! $this->isExpired();
    }

    /**
     * Get the current global accreditation period (start + expiration dates)
     * shared by all approved organizations, or null when no period has been
     * set yet. Both dates come from the same organization row so they stay
     * consistent with each other.
     */
    public static function currentAccreditationPeriod(): ?object
    {
        $latest = static::query()
            ->where('status', 'Approved')
            ->whereNotNull('accreditation_started_at')
            ->whereNotNull('accreditation_expires_at')
            ->orderByDesc('accreditation_expires_at')
            ->first();

        if (! $latest) {
            return null;
        }

        return (object) [
            'started_at' => $latest->accreditation_started_at,
            'expires_at' => $latest->accreditation_expires_at,
        ];
    }

    /**
     * Bare school-year label for an accreditation period, e.g. "2025-2026",
     * or null when the period is incomplete or falls within one calendar year.
     */
    public static function accreditationSchoolYear(mixed $startedAt, mixed $expiresAt): ?string
    {
        $startedAt = $startedAt ? \Illuminate\Support\Carbon::parse($startedAt) : null;
        $expiresAt = $expiresAt ? \Illuminate\Support\Carbon::parse($expiresAt) : null;

        if ($startedAt === null || $expiresAt === null || $expiresAt->year <= $startedAt->year) {
            return null;
        }

        return "{$startedAt->year}-{$expiresAt->year}";
    }

    /**
     * Human readable accreditation period, e.g. "SY 2026-2027 · Aug 1, 2026 – Mar 31, 2027".
     */
    public function accreditationPeriodLabel(): ?string
    {
        if (is_null($this->accreditation_expires_at)) {
            return null;
        }

        $startedAt = $this->accreditation_started_at;
        $expiresAt = $this->accreditation_expires_at;

        $schoolYear = $startedAt && $expiresAt->year > $startedAt->year
            ? "SY {$startedAt->year}-{$expiresAt->year}"
            : "SY {$expiresAt->year}";

        $range = $startedAt
            ? "{$startedAt->format('M j, Y')} - {$expiresAt->format('M j, Y')}"
            : $expiresAt->format('M j, Y');

        return "{$schoolYear} \u{00B7} {$range}";
    }

    /** @return HasMany<OrganizationUser, $this> */
    public function organizationAssignments(): HasMany
    {
        return $this->hasMany(OrganizationUser::class);
    }

    /** @return HasMany<OrganizationUser, $this> */
    public function activeOrganizationAssignments(): HasMany
    {
        return $this->organizationAssignments()->where('membership_status', 'Active');
    }

    /** @return HasMany<AccountManagementAudit, $this> */
    public function accountManagementAudits(): HasMany
    {
        return $this->hasMany(AccountManagementAudit::class);
    }

    /**
     * Get the users that belong to this organization.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->using(OrganizationUser::class)
            ->withPivot('role', 'membership_status', 'management_version')
            ->withTimestamps();
    }

    /**
     * Get active members of this organization.
     */
    public function activeMembers()
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->using(OrganizationUser::class)
            ->wherePivot('membership_status', 'Active')
            ->withPivot('role', 'membership_status', 'management_version')
            ->withTimestamps();
    }

    /**
     * Get officers (admins) of this organization.
     */
    public function officers()
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->using(OrganizationUser::class)
            ->wherePivot('membership_status', 'Active')
            ->where('users.role', 'admin')
            ->withPivot('role', 'membership_status', 'management_version')
            ->withTimestamps();
    }

    /**
     * Get the organization settings
     */
    public function settings()
    {
        return $this->hasOne(OrganizationSettings::class);
    }

    /**
     * Position definitions managed by this organization.
     */
    public function positions()
    {
        return $this->hasMany(OrganizationPosition::class);
    }

    /**
     * Get the department this organization belongs to
     * (null for Non-Academic organizations)
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Check if this organization is a Mother Org
     */
    public function isMotherOrg()
    {
        return $this->type === 'Mother Org';
    }

    /**
     * Check if this organization is a Sub-Org
     */
    public function isSubOrg()
    {
        return $this->type === 'Sub-Org';
    }

    /**
     * Non-Academic organizations publish campus-wide student notifications.
     */
    public function isNonAcademic(): bool
    {
        return strcasecmp(trim((string) $this->type), 'Non-Academic') === 0;
    }

    /**
     * Get Sub-Orgs with the same department_id as this organization
     */
    public function getSubOrgs()
    {
        return Organization::where('department_id', $this->department_id)
            ->where('type', 'Sub-Org')
            ->get();
    }

    /**
     * Get Mother Org with the same department_id as this organization
     */
    public function getMotherOrg()
    {
        return Organization::where('department_id', $this->department_id)
            ->where('type', 'Mother Org')
            ->first();
    }

    /**
     * Get the OSAS chat messages for this organization (shared inbox).
     */
    public function adminChats()
    {
        return $this->hasMany(AdminChat::class)->where('channel', 'osas');
    }

    /**
     * Get all related organization IDs (same department - mother org + sub-orgs).
     */
    public function getRelatedOrgIds()
    {
        if ($this->department_id) {
            return Organization::where('department_id', $this->department_id)
                ->pluck('id')
                ->toArray();
        }

        return [$this->id];
    }

    /**
     * Centralized renew-block check used by admin nav, dashboard and renew page.
     * Returns [blocked, title, message] so messages stay consistent.
     * Gated on: period set → global Start Renewal pressed → per-org queue exists → expiry.
     *
     * @return array{blocked: bool, title: string, message: string}
     */
    public static function renewBlockInfo(?self $organization): array
    {
        $period = static::currentAccreditationPeriod();

        if (! $period || ! $organization?->accreditation_started_at || ! $organization?->accreditation_expires_at) {
            return [
                'blocked' => true,
                'title' => 'Renewal Period Not Set',
                'message' => 'The accreditation renewal period has not been set or opened yet by the OSAS. Please wait for an official announcement or ask OSAS to click Apply + Start Renewal.',
            ];
        }

        // Global gate: OSAS must have pressed Start Renewal at least once since the period was set.
        // This is true when at least one auto_generated pending renewal exists globally.
        try {
            $hasGlobalStart = \Illuminate\Support\Facades\DB::table('apply_orgs')
                ->where('application_type', 'renew')
                ->where('auto_generated', \Illuminate\Support\Facades\DB::raw('TRUE'))
                ->exists();
            if (! $hasGlobalStart) {
                return [
                    'blocked' => true,
                    'title' => 'Renewal Not Yet Started',
                    'message' => 'OSAS hasn\'t clicked Start Renewal yet. Please wait for OSAS to generate the renewal queue before you can renew.',
                ];
            }
        } catch (\Throwable $e) {
            // If table check fails, fail closed — treat as not started.
            return [
                'blocked' => true,
                'title' => 'Renewal Not Yet Started',
                'message' => 'OSAS hasn\'t clicked Start Renewal yet. Please wait for OSAS to generate the renewal queue before you can renew.',
            ];
        }

        // Per-org gate: this specific organization must have its own pending auto renewal queue entry.
        // Without it, direct POST would otherwise auto-create via fallback — blocked per user request.
        if ($organization && $organization->exists) {
            try {
                $hasOrgQueue = \Illuminate\Support\Facades\DB::table('apply_orgs')
                    ->where('organization_id', $organization->id)
                    ->where('application_type', 'renew')
                    ->where('auto_generated', \Illuminate\Support\Facades\DB::raw('TRUE'))
                    ->where('status', 'Pending')
                    ->exists();
                if (! $hasOrgQueue) {
                    return [
                        'blocked' => true,
                        'title' => 'Renewal Not Yet Opened For Your Organization',
                        'message' => 'Your organization\'s renewal queue hasn\'t been opened by OSAS yet. Please ask OSAS to click Start Renewal for expired organizations so your renewal can be submitted.',
                    ];
                }
            } catch (\Throwable $e) {
                return [
                    'blocked' => true,
                    'title' => 'Renewal Not Yet Opened For Your Organization',
                    'message' => 'Your organization\'s renewal queue hasn\'t been opened by OSAS yet. Please wait for OSAS to open it.',
                ];
            }
        }

        if (method_exists($organization, 'isExpired') && ! $organization->isExpired()) {
            $date = $organization->accreditation_expires_at?->format('M d, Y') ?? 'expiry';
            return [
                'blocked' => true,
                'title' => 'Accreditation Still Active',
                'message' => "Your organization accreditation is still active until {$date}. Renewal is not yet open.",
            ];
        }

        return ['blocked' => false, 'title' => '', 'message' => ''];
    }
}
