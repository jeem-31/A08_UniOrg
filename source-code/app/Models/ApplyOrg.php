<?php

namespace App\Models;

use App\Casts\PostgresBoolean;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplyOrg extends Model
{
    use HasFactory;

    protected $table = 'apply_orgs';

    protected $fillable = [
        'organization_id',
        'organization_name',
        'organization_acronym',
        'organization_type',
        'department_id',
        'request_type',
        'application_type',
        'auto_generated',
        'requester_first_name',
        'requester_last_name',
        'requester_email',
        'requester_position',
        'accreditation_form',
        'letter_of_application',
        'constitution_bylaws',
        'renewal_form',
        'adviser_commitment',
        'application_form',
        'certification_of_officers',
        'plan_of_activities',
        'certification_dean',
        'financial_report',
        'list_of_members',
        'accomplishment_reports',
        'other_files',
        'current_step',
        'status',
        'accreditation_year',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'auto_generated' => PostgresBoolean::class,
        ];
    }

    /**
     * Get the admin who reviewed this request
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the officers (users) for this application
     */
    public function officers()
    {
        return $this->hasMany(User::class, 'apply_org_id');
    }

    /**
     * Get the department associated with this request
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the linked organization for this application.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Get the submitted-document projection rows for this application.
     */
    public function documentLibraryEntries(): HasMany
    {
        return $this->hasMany(DocumentLibraryEntry::class, 'apply_org_id');
    }

    /**
     * Relationship alias used by scoped `{document}` library source binding.
     */
    public function documents(): HasMany
    {
        return $this->documentLibraryEntries();
    }

    /**
     * Get the analysis records for this application.
     */
    public function processedDocuments(): HasMany
    {
        return $this->hasMany(ProcessedDocument::class, 'apply_org_id');
    }

    /**
     * Relationship alias used by scoped `{analysis}` route model binding.
     */
    public function analyses(): HasMany
    {
        return $this->processedDocuments();
    }
}
