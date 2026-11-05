<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OsasDocumentSubmission extends Model
{
    use HasFactory;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED_FOR_SIGNATURE = 'approved_for_signature';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'required_document_id',
        'organization_id',
        'submitted_by',
        'file_path',
        'storage_disk',
        'original_filename',
        'mime',
        'size',
        'status',
        'remarks',
        'reviewed_by',
        'reviewed_at',
        'version',
        'processed_document_id',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'version' => 'integer',
            'size' => 'integer',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_SUBMITTED => 'Submitted — waiting for OSAS review',
            self::STATUS_APPROVED_FOR_SIGNATURE => 'For Signature',
            self::STATUS_REJECTED => 'Rejected — please resubmit',
        ];
    }

    public function statusLabel(): string
    {
        return static::statusLabels()[$this->status] ?? (string) $this->status;
    }

    public function isApprovedForSignature(): bool
    {
        return $this->status === self::STATUS_APPROVED_FOR_SIGNATURE;
    }

    public function requiredDocument(): BelongsTo
    {
        return $this->belongsTo(OsasRequiredDocument::class, 'required_document_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function processedDocument(): BelongsTo
    {
        return $this->belongsTo(ProcessedDocument::class, 'processed_document_id');
    }
}
