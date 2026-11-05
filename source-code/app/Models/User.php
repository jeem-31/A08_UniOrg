<?php

namespace App\Models;

use App\Casts\PostgresBoolean;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Organization;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'middle_name',
        'middle_initial',
        'last_name',
        'suffix',
        'email',
        'password',
        'role',
        'student_id',
        'course',
        'year_level',
        'section',
        'department_id',
        'photo',
        'is_active',
        'expires_at',
        'password_changed_at',
        'apply_org_id',
        'temp_position',
    ];

    protected $hidden = [
        'password',
        'password_changed_at',
        'remember_token',
    ];

    /**
     * Append accessors to array/JSON serialization
     */
    protected $appends = [
        'full_name',
    ];

    /**
     * Per-instance cache of the systemless-org fallback state, so the check
     * runs at most one query per request (the session marker alone is cheap,
     * but verifying the user's orgs are still systemless touches the DB).
     */
    private ?bool $systemlessFallbackResolved = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => PostgresBoolean::class,
            'management_version' => 'integer',
        ];
    }

    /**
     * Get the user's full name with middle name and suffix.
     * Example: "Juan Carlos Dela Cruz Jr."
     */
    public function getFullNameAttribute()
    {
        $name = $this->first_name;

        if ($this->middle_name) {
            $name .= ' '.$this->middle_name;
        }

        $name .= ' '.$this->last_name;

        if ($this->suffix) {
            $name .= ' '.$this->suffix;
        }

        return title_case_name($name);
    }

    /**
     * Get the user's short display name (first word of first name + last name).
     * Example: "Juan Carlos Dela Cruz Jr." becomes "Juan Dela Cruz"
     * Middle name and suffix are excluded for brevity in navigation
     */
    public function getShortNameAttribute()
    {
        // Get first word of first name
        $firstWord = explode(' ', trim($this->first_name))[0];

        return title_case_name("{$firstWord} {$this->last_name}");
    }

    /**
     * Get the announcement author name using up to two first-name words and the surname.
     */
    public function getAnnouncementDisplayNameAttribute(): string
    {
        $firstNameWords = preg_split('/\s+/', trim((string) $this->first_name), -1, PREG_SPLIT_NO_EMPTY);
        $givenName = implode(' ', array_slice($firstNameWords, 0, 2));

        return title_case_name(trim($givenName . ' ' . trim((string) $this->last_name)));
    }

    /**
     * Get the user's first name and last name only.
     * Example: "Juan Carlos Dela Cruz Jr." becomes "Juan Carlos Dela Cruz"
     * Excludes middle name and suffix
     */
    public function getFirstLastNameAttribute()
    {
        return title_case_name("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get the user's full name with middle initial instead of full middle name.
     * Example: "Juan Carlos Dela Cruz Jr." becomes "Juan C. Dela Cruz Jr."
     */
    public function getFullNameWithMiddleInitialAttribute()
    {
        $name = $this->first_name;

        if ($this->middle_initial) {
            $name .= ' '.rtrim($this->middle_initial, '.').'.';
        } elseif ($this->middle_name) {
            // If no middle_initial but has middle_name, use first letter
            $name .= ' '.substr($this->middle_name, 0, 1).'.';
        }

        $name .= ' '.$this->last_name;

        if ($this->suffix) {
            $name .= ' '.$this->suffix;
        }

        return title_case_name($name);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'osas_admin']);
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    /**
     * Scope users who have student-facing access for election notifications.
     *
     * Student officers are stored as account-role `admin` so they can manage
     * their organization, but they can still switch to the Student View. Active
     * adviser, dean, and COMELEC memberships are excluded because these are
     * faculty/staff roles and cannot access that view. Staff-only roles
     * (super_admin and osas_admin) are excluded by the allowed-role list.
     */
    public function scopeEligibleForElectionNotifications($query, array $organizationIds)
    {
        $organizations = Organization::whereIn('id', $organizationIds)->get();
        $hasNonAcademicOrganization = $organizations->contains(
            fn (Organization $organization): bool => $organization->isNonAcademic()
        );

        $query = $query
            ->whereIn('role', ['student', 'admin'])
            ->whereDoesntHave('organizations', function ($organizationQuery) {
                $organizationQuery
                    ->where('organization_user.membership_status', 'Active')
                    ->where(function ($roleQuery) {
                        $roleQuery
                            ->whereRaw('LOWER(organization_user.role) LIKE ?', ['%adviser%'])
                            ->orWhereRaw('LOWER(organization_user.role) IN (?, ?)', ['dean', 'comelec']);
                    });
            });

        // Non-Academic announcements are campus-wide. Academic elections are
        // private to the exact organization that owns them.
        if ($hasNonAcademicOrganization) {
            return $query;
        }

        return $query->whereHas('organizations', function ($organizationQuery) use ($organizationIds) {
            $organizationQuery
                ->whereIn('organizations.id', $organizationIds)
                ->where('organization_user.membership_status', 'Active');
        });
    }

    /**
     * Scope student recipients for an organization-aware broadcast.
     *
     * Academic organizations notify active members only. Non-Academic
     * organizations notify every student account, regardless of membership.
     */
    public function scopeEligibleStudentsForOrganizationNotifications($query, $organization)
    {
        $organization = $organization instanceof Organization
            ? $organization
            : Organization::find($organization);

        $query = $query->where('role', 'student');

        if (!$organization) {
            return $query->whereRaw('1 = 0');
        }

        if ($organization->isNonAcademic()) {
            return $query;
        }

        return $query->whereHas('organizations', function ($organizationQuery) use ($organization) {
            $organizationQuery
                ->where('organizations.id', $organization->id)
                ->where('organization_user.membership_status', 'Active');
        });
    }

    /**
     * Get the user's active view ('admin' or 'student').
     * Defaults to 'admin' when not set (for admin-role users).
     */
    public function getActiveView(): string
    {
        // COMELEC is a staff-only account even though its users table role is
        // `admin`; never allow a stale active_view value to expose student UI.
        if ($this->isComelec()) {
            return 'admin';
        }

        // Users routed to their department Mother Org because their own org
        // has no system yet are locked into the Student View for the session.
        if ($this->isSystemlessFallback()) {
            return 'student';
        }

        return $this->active_view ?? 'admin';
    }

    /**
     * Only plain 'admin' role users are allowed to switch between
     * Admin View and Student View (super_admin, osas_admin, dean cannot).
     * Advisers (faculty/prof, not actual students) are excluded even if
     * their account role is 'admin'.
     */
    public function canSwitchView(): bool
    {
        // Systemless-org fallback users are forced into the Student View and
        // cannot switch until their organization gains a system. The session
        // fallback marker clears itself automatically, which restores the
        // switch button, org switcher, and the last remembered view.
        if ($this->isSystemlessFallback()) {
            return false;
        }

        return $this->role === 'admin' && ! $this->isAdviser() && ! $this->isComelec();
    }

    /**
     * Whether this session is in "systemless org fallback" mode: the user's
     * active organizations all lack a system, so they were routed to their
     * department Mother Org in the Student View. The marker clears itself
     * once any of the user's active organizations gains a system.
     */
    public function isSystemlessFallback(): bool
    {
        if ($this->systemlessFallbackResolved !== null) {
            return $this->systemlessFallbackResolved;
        }

        if (session('systemless_fallback') !== true) {
            return $this->systemlessFallbackResolved = false;
        }

        // The fallback only applies while the user's organizations are all
        // still systemless. Once any active org gains a system, drop the
        // marker so the user returns to the normal flow.
        if ($this->activeOrganizations()->whereRaw('organizations.has_system IS TRUE')->exists()) {
            session()->forget('systemless_fallback');

            return $this->systemlessFallbackResolved = false;
        }

        return $this->systemlessFallbackResolved = true;
    }

    /**
     * Whether the user holds an Adviser-type position (e.g. "Adviser",
     * "Organization Adviser", "Senior Adviser", "Junior Adviser") in any
     * of their organization memberships. Advisers are faculty/professors,
     * not students, so they should never be offered the Student View.
     */
    public function isAdviser(): bool
    {
        return $this->organizations()
            ->where('organization_user.membership_status', 'Active')
            ->get()
            ->contains(function ($organization) {
                return str_contains(strtolower($organization->pivot->role ?? ''), 'adviser');
            });
    }

    /**
     * Whether the user holds the dedicated COMELEC organization position.
     */
    public function isComelec(): bool
    {
        return $this->organizations()
            ->where('organization_user.membership_status', 'Active')
            ->whereRaw('UPPER(organization_user.role) = ?', ['COMELEC'])
            ->exists();
    }

    /**
     * Whether the user holds a Dean-type position (e.g. "Dean", "Assoc.
     * Dean", "Dean/Assoc. Dean") in any of their active organization
     * memberships. Deans are staff and have no Student View to fall back to.
     */
    public function hasDeanMembership(): bool
    {
        return $this->organizations()
            ->where('organization_user.membership_status', 'Active')
            ->whereRaw('LOWER(organization_user.role) LIKE ?', ['%dean%'])
            ->exists();
    }

    /**
     * Whether the user should currently be treated as a student
     * for navigation/dashboard purposes.
     */
    public function isEffectivelyStudent(): bool
    {
        // Systemless-org fallback users are always treated as students for
        // navigation and dashboard purposes, regardless of account role.
        if ($this->isSystemlessFallback()) {
            return true;
        }

        return $this->role === 'student'
            || ($this->role === 'admin' && $this->getActiveView() === 'student');
    }

    /**
     * Whether the user should currently be treated as an admin
     * for navigation/dashboard purposes.
     */
    public function isEffectivelyAdmin(): bool
    {
        if (! $this->isAdmin()) {
            return false;
        }

        if ($this->role === 'admin') {
            return $this->getActiveView() !== 'student';
        }

        return true;
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function studentPayments()
    {
        return $this->hasMany(StudentPayment::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    public function documents()
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function sharedDocuments()
    {
        return $this->hasMany(SharedDocument::class, 'student_id');
    }

    public function libraryResources()
    {
        return $this->hasMany(LibraryResource::class);
    }

    public function edushareItems()
    {
        return $this->hasMany(EdushareItem::class);
    }

    public function edushareFolders()
    {
        return $this->hasMany(EdushareFolder::class);
    }

    public function edushareShares()
    {
        return $this->hasMany(EdushareShare::class, 'shared_by');
    }

    public function adminChats()
    {
        return $this->hasMany(AdminChat::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
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
        return $this->hasMany(AccountManagementAudit::class, 'affected_user_id');
    }

    /** @return HasMany<AccountManagementAudit, $this> */
    public function actedAccountManagementAudits(): HasMany
    {
        return $this->hasMany(AccountManagementAudit::class, 'actor_user_id');
    }

    /**
     * Get the organizations that the user belongs to.
     */
    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->using(OrganizationUser::class)
            ->withPivot('role', 'membership_status', 'management_version')
            ->withTimestamps();
    }

    /**
     * Get the department that the user belongs to
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user's active organizations.
     */
    public function activeOrganizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->using(OrganizationUser::class)
            ->wherePivot('membership_status', 'Active')
            ->withPivot('role', 'membership_status', 'management_version')
            ->withTimestamps();
    }

    public function webauthnCredential()
    {
        return $this->hasOne(WebAuthnCredential::class);
    }

    public function hasWebAuthnCredential(): bool
    {
        return $this->webauthnCredential()->exists();
    }

    /**
     * Get the user's position in a specific organization
     */
    public function getPositionInOrganization(int $organizationId): ?string
    {
        $organization = $this->organizations()
            ->where('organization_id', $organizationId)
            ->where('organization_user.membership_status', 'Active')
            ->first();

        return $organization?->pivot->role;
    }
}
