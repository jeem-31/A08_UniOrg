<?php

namespace App\Models;

use App\Casts\PostgresBoolean;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class OsasRequiredDocument extends Model
{
    use HasFactory;

    public const KEY_ACCOMPLISHMENT = 'accomplishment_report';

    public const KEY_NARRATIVE = 'narrative_report';

    public const KEY_FINANCIAL = 'financial_report';

    public const KEY_PLAN = 'plan_of_activities';

    public const KEY_OTHER = 'other';

    protected $fillable = [
        'department_id',
        'organization_id',
        'document_key',
        'custom_title',
        'description',
        'deadline_at',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
            // Plain 'boolean' binds as integer 1 on writes, which PostgreSQL
            // rejects (42804). PostgresBoolean stores TRUE/FALSE instead.
            'is_active' => PostgresBoolean::class,
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::KEY_ACCOMPLISHMENT => 'Accomplishment Report',
            self::KEY_NARRATIVE => 'Narrative Report',
            self::KEY_FINANCIAL => 'Financial Report',
            self::KEY_PLAN => 'Plan of Activities',
            self::KEY_OTHER => 'Other (custom)',
        ];
    }

    public function displayTitle(): string
    {
        if ($this->document_key === self::KEY_OTHER) {
            return trim((string) $this->custom_title) !== ''
                ? (string) $this->custom_title
                : 'Other Document';
        }

        return static::typeLabels()[$this->document_key] ?? (string) $this->document_key;
    }

    /**
     * PostgreSQL has no boolean = integer operator, so a plain
     * where('is_active', true) compiles to "is_active" = 1 and 500s
     * on pgsql while silently passing on SQLite. Always go through
     * this scope for boolean filtering.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', DB::raw('TRUE'));
    }

    public function isBulk(): bool
    {
        return $this->organization_id === null;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(OsasDocumentSubmission::class, 'required_document_id');
    }
}
