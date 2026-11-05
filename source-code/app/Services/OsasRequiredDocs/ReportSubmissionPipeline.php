<?php

namespace App\Services\OsasRequiredDocs;

use App\Jobs\ProcessDocumentAnalysis;
use App\Models\ApplyOrg;
use App\Models\Organization;
use App\Models\OsasDocumentSubmission;
use App\Models\OsasRequiredDocument;
use App\Services\DocumentIntelligence\DocumentAnalysisRegistrar;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;
use App\Services\DocumentIntelligence\SupportedDocumentCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bridges OSAS required-document submissions into the existing
 * accreditation document-intelligence pipeline without touching
 * its tables: every submission is registered as an `other_files`
 * slot on a per-organization linker ApplyOrg row, so summary,
 * retry, and re-summarize all work through the existing jobs
 * and OSAS analysis endpoints.
 */
final class ReportSubmissionPipeline
{
    public function __construct(
        private readonly DocumentAnalysisRegistrar $registrar,
        private readonly SupportedDocumentCatalog $catalog,
    ) {}

    /**
     * Requirements visible to an organization: direct rows plus
     * bulk (department-wide) rows for its department.
     */
    public function requirementsForOrganization(int $organizationId): mixed
    {
        $organization = Organization::find($organizationId);
        if ($organization === null) {
            return OsasRequiredDocument::query()->whereRaw('1 = 0');
        }

        return OsasRequiredDocument::query()
            ->with(['department', 'organization'])
            ->active()
            ->where(function ($query) use ($organization) {
                $query->where('organization_id', $organization->getKey());
                if ($organization->department_id) {
                    $query->orWhere(function ($bulk) use ($organization) {
                        $bulk->whereNull('organization_id')
                            ->where('department_id', $organization->department_id);
                    });
                }
            })
            ->orderBy('deadline_at')
            ->orderBy('id');
    }

    public function analysisSlot(OsasRequiredDocument $requirement, int $organizationId): string
    {
        return "reqdoc-{$requirement->getKey()}-org-{$organizationId}";
    }

    /**
     * School year label (e.g. 2026-2027) the organization currently belongs
     * to. Prefers the global accreditation period, then the organization's
     * own accreditation dates, then a dated org in the same department,
     * then a calendar fallback (SY starts August).
     */
    public function currentSchoolYear(Organization $organization): ?string
    {
        $period = Organization::currentAccreditationPeriod();
        if ($period && $period->started_at && $period->expires_at) {
            $schoolYear = Organization::accreditationSchoolYear($period->started_at, $period->expires_at);
            if (is_string($schoolYear) && $schoolYear !== '') {
                return $schoolYear;
            }
        }

        if ($organization->accreditation_started_at && $organization->accreditation_expires_at) {
            $schoolYear = Organization::accreditationSchoolYear(
                $organization->accreditation_started_at,
                $organization->accreditation_expires_at,
            );
            if (is_string($schoolYear) && $schoolYear !== '') {
                return $schoolYear;
            }
        }

        if ($organization->department_id) {
            $departmentYear = Organization::query()
                ->where('department_id', $organization->department_id)
                ->where('status', 'Approved')
                ->whereNotNull('accreditation_started_at')
                ->whereNotNull('accreditation_expires_at')
                ->orderByDesc('accreditation_expires_at')
                ->first();
            if ($departmentYear) {
                $schoolYear = Organization::accreditationSchoolYear(
                    $departmentYear->accreditation_started_at,
                    $departmentYear->accreditation_expires_at,
                );
                if (is_string($schoolYear) && $schoolYear !== '') {
                    return $schoolYear;
                }
            }
        }

        $now = now();
        $year = (int) $now->format('Y');

        return ((int) $now->format('n') >= 8)
            ? "{$year}-".($year + 1)
            : ($year - 1)."-{$year}";
    }

    /**
     * Internal linker row, one per organization per school year so library
     * entries land in the year folder of the submission. application_type
     * stays NULL (real applications always carry apply/renew) so
     * accreditation queues never pick these rows up.
     */
    public function linkerForOrganization(Organization $organization, ?string $schoolYear = null): ApplyOrg
    {
        $schoolYear ??= $this->currentSchoolYear($organization);

        return ApplyOrg::query()->firstOrCreate(
            [
                'organization_id' => $organization->getKey(),
                'request_type' => 'request',
                'application_type' => null,
                'auto_generated' => DB::raw('TRUE'),
                'accreditation_year' => $schoolYear,
            ],
            [
                'organization_name' => $organization->name,
                'organization_acronym' => $organization->org_acronym,
                'organization_type' => $organization->type,
                'department_id' => $organization->department_id,
                'requester_first_name' => 'OSAS',
                'requester_last_name' => 'System',
                'requester_email' => 'osas@system.local',
                'status' => 'Approved',
                'current_step' => 6,
                'admin_notes' => '[report-linker] Internal row for OSAS required-document submissions. Not an accreditation request.',
            ],
        );
    }

    /**
     * Store the uploaded file, point the linker slot at it, register the
     * analysis, and queue processing. Returns dispatch commands to run
     * after the caller's transaction commits.
     *
     * @return array{path:string,disk:string,name:string,source_version:string,source_fingerprint:string,commands:list}
     */
    public function storeAndRegister(
        ApplyOrg $linker,
        OsasRequiredDocument $requirement,
        int $organizationId,
        UploadedFile $file,
    ): array {
        $disk = storage_disk();
        $fingerprint = hash_file('sha256', $file->getRealPath());
        if (! is_string($fingerprint)) {
            throw new \RuntimeException('Unable to fingerprint the uploaded document.');
        }

        $sourceVersion = (string) Str::uuid();
        $path = $file->store('osas-required-documents', $disk);
        if (! is_string($path) || trim($path) === '') {
            throw new \RuntimeException('Unable to store the uploaded document.');
        }

        $slot = $this->analysisSlot($requirement, $organizationId);
        $entry = [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'slot' => $slot,
            'storage_disk' => $disk,
            'source_version' => $sourceVersion,
            'source_fingerprint' => strtolower($fingerprint),
        ];

        $linker->other_files = json_encode(
            $this->replaceSlot($this->decodeOtherFiles($linker->other_files), $entry),
            JSON_THROW_ON_ERROR,
        );
        $linker->save();
        $linker->refresh();

        $descriptor = new StoredDocumentDescriptor(
            (int) $linker->getKey(),
            'other_files',
            $slot,
            $this->catalog->definition('other_files')->label,
            $entry['name'],
            $disk,
            $path,
            $sourceVersion,
            strtolower($fingerprint),
        );

        // Caller owns the transaction; registrar enforces it.
        $commands = $this->registrar->register($linker, [$descriptor]);

        return [
            'path' => $path,
            'disk' => $disk,
            'name' => $entry['name'],
            'source_version' => $sourceVersion,
            'source_fingerprint' => strtolower($fingerprint),
            'commands' => $commands,
        ];
    }

    public function dispatchCommands(array $commands): void
    {
        foreach ($commands as $command) {
            try {
                ProcessDocumentAnalysis::dispatch(
                    $command->analysisId,
                    $command->sourceVersion,
                    $command->cycleId,
                );
            } catch (\Throwable $failure) {
                Log::error('Failed to dispatch required-document analysis.', [
                    'analysis_id' => $command->analysisId,
                    'message' => $failure->getMessage(),
                ]);
            }
        }
    }

    public function currentAnalysisId(ApplyOrg $linker, string $slot): ?int
    {
        $analysis = $linker->processedDocuments()
            ->where('document_field', 'other_files')
            ->where('document_slot', $slot)
            ->latest('id')
            ->first();

        return $analysis ? (int) $analysis->getKey() : null;
    }

    /**
     * @param  mixed  $raw
     * @return list<array<string,mixed>>
     */
    private function decodeOtherFiles(mixed $raw): array
    {
        if (is_string($raw) && trim($raw) !== '') {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                return [];
            }
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, 'is_array'));
            }
        }

        return [];
    }

    /**
     * @param  list<array<string,mixed>>  $entries
     * @return list<array<string,mixed>>
     */
    private function replaceSlot(array $entries, array $entry): array
    {
        $replaced = false;
        foreach ($entries as $index => $existing) {
            if (($existing['slot'] ?? null) === $entry['slot']) {
                $entries[$index] = $entry;
                $replaced = true;
            }
        }
        if (! $replaced) {
            $entries[] = $entry;
        }

        return array_values($entries);
    }

    public function deleteStoredFile(OsasDocumentSubmission $submission): void
    {
        try {
            Storage::disk($submission->storage_disk)->delete($submission->file_path);
        } catch (\Throwable $e) {
            Log::warning('Unable to delete required-document file.', [
                'submission_id' => $submission->getKey(),
                'message' => $e->getMessage(),
            ]);
        }
    }
}
