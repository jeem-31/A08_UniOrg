<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OrganizationUser extends Pivot
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'organization_user';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'organization_id',
        'role',
        'membership_status',
    ];

    protected function casts(): array
    {
        return [
            'management_version' => 'integer',
        ];
    }

    /**
     * Human-readable display label for a role stored in organization_user.
     *
     * Roles are stored in their canonical display form (e.g. "Business Manager"),
     * but legacy rows may use abbreviated forms like "1st Yr Rep". This normalizes
     * "Yr" -> "Year" and "Rep" -> "Representative" so the UI always shows
     * "1st Year Representative" even when the DB still holds the abbreviated form.
     *
     * Use this everywhere a role is shown to the user.
     */
    public static function formatDisplayLabel(string $role): string
    {
        $role = trim($role);
        if ($role === '') {
            return $role;
        }

        // Normalize Year Representative abbreviations: "1st Yr Rep" -> "1st Year Representative"
        // Handles: "1st Yr Rep", "1st yr rep", "1st Yr Representative", "1st Year Rep", etc.
        $normalized = preg_replace('/\bYr\.?\b/i', 'Year', $role);
        $normalized = preg_replace('/\bRep\.?\b/i', 'Representative', $normalized);
        $normalized = trim((string) preg_replace('/\s+/', ' ', $normalized));

        // Fix ordinal casing: "1ST" -> "1st", "2ND" -> "2nd", etc. for consistent display
        $normalized = preg_replace_callback('/(\d+)(ST|ND|RD|TH)\b/i', static fn (array $m): string => $m[1] . strtolower($m[2]), $normalized);

        // Ensure "Year Representative" is properly capitalized when it appears
        // (covers cases where input was fully lowercased)
        if (preg_match('/^\d+(st|nd|rd|th)\s+year\s+representative$/i', $normalized)) {
            $normalized = preg_replace_callback('/^\s*(\d+)(st|nd|rd|th)\s+year\s+representative\s*$/i', static fn (array $m): string => $m[1] . strtolower($m[2]) . ' Year Representative', $normalized);
        }

        return $normalized;
    }

    /**
     * Convenience instance wrapper around formatDisplayLabel().
     */
    public function displayLabel(): string
    {
        return self::formatDisplayLabel((string) $this->getAttribute('role'));
    }

    /**
     * Create an assignment through the observed pivot model.
     *
     * Services must use this instead of raw pivot-table inserts so permission
     * cache invalidation continues to run for newly occupied positions.
     */
    public static function createAssignment(
        User $user,
        Organization $organization,
        string $role,
        string $membershipStatus,
    ): self {
        /** @var self $assignment */
        $assignment = static::query()->create([
            'user_id' => $user->getKey(),
            'organization_id' => $organization->getKey(),
            'role' => $role,
            'membership_status' => $membershipStatus,
        ]);

        return $assignment;
    }

    /**
     * Update a locked assignment through the observed model instead of a raw query.
     */
    public function replaceRoleAndMembership(string $role, string $membershipStatus): bool
    {
        $this->fill([
            'role' => $role,
            'membership_status' => $membershipStatus,
        ]);

        return $this->save();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
