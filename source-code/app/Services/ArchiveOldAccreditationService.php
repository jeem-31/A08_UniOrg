<?php

namespace App\Services;

use App\Models\ApplyOrg;
use App\Models\ApplyOrgDocumentArchive;
use App\Models\ArchivedDocumentLibraryEntry;
use App\Models\ArchivedProcessedDocument;
use App\Models\ArchivedProcessingAttempt;
use App\Models\DocumentLibraryEntry;
use App\Models\Organization;
use App\Models\ProcessedDocument;
use App\Services\DocumentLibrary\AccreditationYear;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArchiveOldAccreditationService
{
    /**
     * Snapshot the latest Approved Recognition per accreditation period
     * for this organization into apply_org_document_archives.
     * Called BEFORE queuing a Pending Renewal so docs survive hidden/deleted Workbench card.
     * Idempotent per (organization_id, accreditation_year).
     *
     * @return ApplyOrgDocumentArchive|null archived row or null if nothing to archive
     */
    public function archiveForOrganization(Organization $org): ?ApplyOrgDocumentArchive
    {
        // Find latest Approved record that still holds docs (supports both apply and renew cycles)
        // Previously filtered only 'apply' which missed Approved renew rows (e.g., id 14) and prevented archiving before reuse.
        $source = ApplyOrg::where('organization_id', $org->id)
            ->where('status', 'Approved')
            ->orderByDesc('created_at')
            ->first();

        if (! $source) {
            return null;
        }

        $period = $this->resolveAccreditationYear($source);
        // Per-accreditation-period idempotency — same org+year not archived twice
        $exists = ApplyOrgDocumentArchive::where('organization_id', $org->id)
            ->where('source_apply_org_id', $source->id)
            ->when($period !== null && trim($period) !== '', fn ($q) => $q->where('accreditation_year', $period))
            ->exists();

        if ($exists) {
            return null;
        }

        return DB::transaction(function () use ($org, $source, $period) {
            $archive = ApplyOrgDocumentArchive::create([
                'organization_id' => $org->id,
                'source_apply_org_id' => $source->id,
                'department_id' => $source->department_id,
                'archived_by' => Auth::id(),
                'organization_name' => $source->organization_name,
                'organization_acronym' => $source->organization_acronym,
                'organization_type' => $source->organization_type,
                'request_type' => $source->request_type,
                'application_type' => $source->application_type,
                'auto_generated' => $source->auto_generated ? DB::raw('TRUE') : DB::raw('FALSE'),
                'requester_first_name' => $source->requester_first_name,
                'requester_last_name' => $source->requester_last_name,
                'requester_email' => $source->requester_email,
                'requester_position' => $source->requester_position,
                'accreditation_form' => $source->accreditation_form,
                'letter_of_application' => $source->letter_of_application,
                'constitution_bylaws' => $source->constitution_bylaws,
                'renewal_form' => $source->renewal_form,
                'adviser_commitment' => $source->adviser_commitment,
                'application_form' => $source->application_form,
                'certification_of_officers' => $source->certification_of_officers,
                'plan_of_activities' => $source->plan_of_activities,
                'certification_dean' => $source->certification_dean,
                'financial_report' => $source->financial_report,
                'list_of_members' => $source->list_of_members,
                'accomplishment_reports' => $source->accomplishment_reports,
                'other_files' => $source->other_files,
                'current_step' => $source->current_step,
                'status' => $source->status,
                'accreditation_year' => $period,
                'admin_notes' => $source->admin_notes,
                'reviewed_by' => $source->reviewed_by,
                'reviewed_at' => $source->reviewed_at,
                'archived_at' => now(),
            ]);

            // Full copy of document_library_entries per period (same columns)
            $libraryEntries = DocumentLibraryEntry::where('apply_org_id', $source->id)->get();
            foreach ($libraryEntries as $entry) {
                ArchivedDocumentLibraryEntry::create([
                    'archive_id' => $archive->id,
                    'original_apply_org_id' => $source->id,
                    'document_field' => $entry->document_field,
                    'document_slot' => $entry->document_slot,
                    'requirement_label' => $entry->requirement_label,
                    'original_filename' => $entry->original_filename,
                    'storage_disk' => $entry->storage_disk,
                    'file_path' => $entry->file_path,
                    'source_version' => $entry->source_version,
                    'source_fingerprint' => $entry->source_fingerprint,
                ]);
            }

            // Full copy of processed_documents + attempts (full AI output per requirement)
            $processed = ProcessedDocument::where('apply_org_id', $source->id)->get();
            foreach ($processed as $pd) {
                $archivedPd = ArchivedProcessedDocument::create([
                    'archive_id' => $archive->id,
                    'original_apply_org_id' => $source->id,
                    'original_processed_document_id' => $pd->id,
                    'document_field' => $pd->document_field,
                    'document_slot' => $pd->document_slot,
                    'original_filename' => $pd->original_filename,
                    'file_path' => $pd->file_path,
                    'storage_disk' => $pd->storage_disk,
                    'source_version' => $pd->source_version,
                    'source_fingerprint' => $pd->source_fingerprint,
                    'document_type' => $pd->document_type,
                    'document_type_name' => $pd->document_type_name,
                    'summary' => $pd->summary,
                    'tags' => $pd->tags,
                    'key_information' => $pd->key_information,
                    'extracted_text' => $pd->extracted_text,
                    'confidence_score' => $pd->confidence_score,
                    'processing_status' => $pd->processing_status,
                    'error_message' => $pd->error_message,
                    'processed_at' => $pd->processed_at,
                    'association_ready_at' => $pd->association_ready_at,
                    'active_cycle_id' => $pd->active_cycle_id,
                    'cycle_kind' => $pd->cycle_kind,
                    'cycle_attempt_count' => $pd->cycle_attempt_count,
                    'processing_started_at' => $pd->processing_started_at,
                    'processing_duration_ms' => $pd->processing_duration_ms,
                    'output_source_version' => $pd->output_source_version,
                    'failure_code' => $pd->failure_code,
                    'summary_version' => $pd->summary_version,
                    'summary_processed_at' => $pd->summary_processed_at,
                    'resummarization_status' => $pd->resummarization_status,
                    'resummarization_cycle_id' => $pd->resummarization_cycle_id,
                    'resummarization_error_message' => $pd->resummarization_error_message,
                    'last_resummarization_changed' => $pd->last_resummarization_changed,
                ]);

                $attempts = DB::table('document_processing_attempts')->where('processed_document_id', $pd->id)->get();
                foreach ($attempts as $att) {
                    ArchivedProcessingAttempt::create([
                        'archived_processed_document_id' => $archivedPd->id,
                        'original_attempt_id' => $att->id,
                        'cycle_id' => $att->cycle_id,
                        'source_version' => $att->source_version,
                        'operation' => $att->operation,
                        'ordinal' => $att->ordinal,
                        'outcome' => $att->outcome,
                        'failure_class' => $att->failure_class,
                        'provider_status_code' => $att->provider_status_code,
                        'sanitized_message' => $att->sanitized_message,
                        'started_at' => $att->started_at,
                        'finished_at' => $att->finished_at,
                        'duration_ms' => $att->duration_ms,
                    ]);
                }
            }

            // Verify full copy counts — keep live row for update in place (don't hard delete)
            $libCount = DocumentLibraryEntry::where('apply_org_id', $source->id)->count();
            $archLibCount = ArchivedDocumentLibraryEntry::where('archive_id', $archive->id)->count();
            $pdCount = ProcessedDocument::where('apply_org_id', $source->id)->count();
            $archPdCount = ArchivedProcessedDocument::where('archive_id', $archive->id)->count();
            if ($libCount !== $archLibCount || $pdCount !== $archPdCount) {
                throw new \RuntimeException("Archive copy mismatch for apply_org {$source->id}: lib {$libCount}/{$archLibCount} pd {$pdCount}/{$archPdCount}");
            }

            // Don't hard delete — keep apply_org row for update in place (users/files stay, no new row)
            // Live document entries will be cleared by ExpiredRenewalGenerator after this, to make room for new docs
            return $archive;
        });
    }

    /**
     * Resolve the accreditation year for archiving. The value must come from
     * OSAS settings (/osas/settings?tab=accreditation) — the global current
     * period — and must never be left empty right after expiration.
     * Fallback order: valid source year → most common school_year from AI output
     * → current period's school year from settings.
     */
    private function resolveAccreditationYear(ApplyOrg $source): ?string
    {
        $candidate = trim((string) ($source->accreditation_year ?? ''));
        if ($candidate !== '' && AccreditationYear::parse($candidate) !== null) {
            return $candidate;
        }

        // Try to infer from the most common school_year among completed analyses
        $tally = [];
        $processed = ProcessedDocument::where('apply_org_id', $source->id)
            ->where('processing_status', 'completed')
            ->get();
        foreach ($processed as $pd) {
            $info = $pd->key_information;
            if (is_string($info)) {
                $info = json_decode($info, true);
            }
            $schoolYear = is_array($info) ? ($info['school_year'] ?? null) : null;
            $parsed = is_string($schoolYear) ? AccreditationYear::parse($schoolYear) : null;
            if ($parsed === null) {
                continue;
            }
            $key = $parsed->key();
            $tally[$key] = ($tally[$key] ?? 0) + 1;
        }
        if ($tally !== []) {
            arsort($tally);
            return array_key_first($tally);
        }

        // Fallback to the global current period from OSAS settings
        $period = Organization::currentAccreditationPeriod();
        if ($period) {
            $schoolYear = Organization::accreditationSchoolYear($period->started_at, $period->expires_at);
            if ($schoolYear && AccreditationYear::parse($schoolYear) !== null) {
                return $schoolYear;
            }
        }

        return $candidate !== '' ? $candidate : null;
    }
}
