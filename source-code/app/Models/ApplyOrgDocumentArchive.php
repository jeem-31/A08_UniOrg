<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplyOrgDocumentArchive extends Model
{
    use HasFactory;

    protected $table = 'apply_org_document_archives';

    protected $fillable = [
        'organization_id',
        'source_apply_org_id',
        'department_id',
        'archived_by',
        'organization_name',
        'organization_acronym',
        'organization_type',
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
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'archived_at' => 'datetime',
            'auto_generated' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function sourceApplyOrg(): BelongsTo
    {
        return $this->belongsTo(ApplyOrg::class, 'source_apply_org_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function archivedLibraryEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ArchivedDocumentLibraryEntry::class, 'archive_id');
    }

    public function archivedProcessedDocuments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ArchivedProcessedDocument::class, 'archive_id');
    }
}
