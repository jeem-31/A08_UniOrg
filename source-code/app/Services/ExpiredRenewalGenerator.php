<?php

namespace App\Services;

use App\Models\ApplyOrg;
use App\Models\DocumentLibraryEntry;
use App\Models\Organization;
use App\Models\ProcessedDocument;
use App\Notifications\RenewalRequiredNotification;
use App\Services\DocumentLibrary\AccreditationYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpiredRenewalGenerator
{
    /**
     * Generate a pending renewal record for every approved organization whose
     * accreditation has already expired, so they surface in the Renewal filter.
     * Records already holding a pending renewal are skipped (safe to re-run).
     *
     * @return array{created: int, skipped: int}
     */
    public function generate(): array
    {
        $expiredOrgs = Organization::where('status', 'Approved')
            ->whereNotNull('accreditation_expires_at')
            ->get()
            ->filter(fn (Organization $org) => $org->accreditationStatus() === 'expired');

        $created = 0;
        $skipped = 0;

        foreach ($expiredOrgs as $org) {
            $didSkip = false;
            $txException = null;
            try {
                DB::transaction(function () use ($org, &$didSkip) {
                    // Atomic skip: any renewal queue (Pending) or submitted renewal (For-Checking) means already have renewal — skip, don't create duplicate.
                    // Must be inside transaction with lock to be race-proof against double-click / two tabs.
                    // Per user choice: 1 pending renew lang hangga't hindi na-approve (dun lang ako approve)
                    $hasPending = ApplyOrg::where('organization_id', $org->id)
                        ->where('application_type', 'renew')
                        ->whereIn('status', ['Pending', 'For-Checking'])
                        ->lockForUpdate()
                        ->exists();

                    if ($hasPending) {
                        $didSkip = true;
                        return;
                    }

                    // 1) Snapshot old Approved Recognition per accreditation period to archive_lib BEFORE clearing live row
                    $archived = app(ArchiveOldAccreditationService::class)->archiveForOrganization($org);

                    // Accreditation year for the new Pending renewal must come from OSAS settings
                    // (/osas/settings?tab=accreditation) — the global current period.
                    // This ensures it is never left empty right after expiration.
                    $currentPeriod = Organization::currentAccreditationPeriod();
                    $newSchoolYear = $currentPeriod
                        ? Organization::accreditationSchoolYear($currentPeriod->started_at, $currentPeriod->expires_at)
                        : null;

                    // If the current period is not a valid school year (e.g., same year, or not set yet),
                    // derive the next year from the archived old year's school year so the renewal
                    // is never left empty and always moves forward (2025-2026 -> 2026-2027).
                    if ($newSchoolYear === null || AccreditationYear::parse($newSchoolYear) === null) {
                        $archivedYear = $archived?->accreditation_year ?? null;
                        $parsedArchived = is_string($archivedYear) ? AccreditationYear::parse($archivedYear) : null;
                        if ($parsedArchived !== null) {
                            $candidate = ($parsedArchived->startingYear + 1).'-'.($parsedArchived->endingYear + 1);
                            if (AccreditationYear::parse($candidate) !== null) {
                                $newSchoolYear = $candidate;
                            }
                        }
                    }

                    // 2) Update same apply_org row in place — no new row, set accreditation_year from settings, clear live docs for new upload
                    // Find latest Approved for this org (supports both initial Recognition 'apply' and already-renewed 'request' rows)
                    // Previous code filtered only 'apply' and caused duplicate when org already had an Approved renew (e.g., id 14) — single-click would CREATE id 17 instead of updating 14.
                    $source = ApplyOrg::where('organization_id', $org->id)
                        ->where('status', 'Approved')
                        ->orderByDesc('created_at')
                        ->lockForUpdate()
                        ->first();

                    // If already archived and source still Approved, update it to Pending Renewal in place
                    if ($source) {
                        // Clear existing document entries from live tables after archiving (they are safe in archived_* per period)
                        DocumentLibraryEntry::where('apply_org_id', $source->id)->delete();
                        ProcessedDocument::where('apply_org_id', $source->id)->delete();

                        $source->update([
                            'request_type' => 'request',
                            'application_type' => 'renew',
                            'status' => 'Pending',
                            'auto_generated' => DB::raw('TRUE'),
                            'current_step' => 1,
                            'accreditation_year' => $newSchoolYear,
                            'admin_notes' => 'Auto-generated: accreditation expired on '.$org->accreditation_expires_at?->toDateString().'. Organization must re-upload renewal documents.',
                            'reviewed_by' => null,
                            'reviewed_at' => null,
                            // Clear live document columns so new renewal docs have empty slots
                            'accreditation_form' => null,
                            'letter_of_application' => null,
                            'constitution_bylaws' => null,
                            'renewal_form' => null,
                            'adviser_commitment' => null,
                            'application_form' => null,
                            'certification_of_officers' => null,
                            'plan_of_activities' => null,
                            'certification_dean' => null,
                            'financial_report' => null,
                            'list_of_members' => null,
                            'accomplishment_reports' => null,
                            'other_files' => null,
                        ]);
                    } else {
                        // Fallback if no Approved source found — create new (rare: org approved without apply row)
                        // Unique index will guard against duplicate if race hits here.
                        ApplyOrg::create([
                            'organization_id' => $org->id,
                            'organization_name' => $org->name,
                            'organization_acronym' => $org->org_acronym,
                            'organization_type' => $org->type === 'Sub-Org' ? 'Sub Org' : $org->type,
                            'department_id' => $org->department_id,
                            'request_type' => 'request',
                            'application_type' => 'renew',
                            'auto_generated' => DB::raw('TRUE'),
                            'status' => 'Pending',
                            'current_step' => 1,
                            'accreditation_year' => $newSchoolYear,
                            'admin_notes' => 'Auto-generated: accreditation expired on '.$org->accreditation_expires_at?->toDateString().'. Organization must re-upload renewal documents.',
                        ]);
                    }
                });
            } catch (\Illuminate\Database\QueryException $e) {
                // Unique partial index violation (23505) means concurrent transaction already created the row — treat as skipped
                $msg = $e->getMessage();
                if (str_contains($msg, '23505') || str_contains($msg, 'duplicate') || str_contains($msg, 'unique') || str_contains($msg, 'apply_orgs_one_pending_renew_per_org')) {
                    Log::warning('[ExpiredRenewalGenerator] Duplicate blocked by unique index, treating as skipped', ['org_id' => $org->id, 'error' => $e->getMessage()]);
                    $didSkip = true;
                } else {
                    Log::error('[ExpiredRenewalGenerator] Transaction failed for org '.$org->id.': '.$e->getMessage());
                    // Count as skipped to keep counters consistent, but don't notify dean
                    $didSkip = true;
                    $txException = $e;
                }
            } catch (\Throwable $e) {
                Log::error('[ExpiredRenewalGenerator] Failed for org '.$org->id.': '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
                $didSkip = true;
                $txException = $e;
            }

            if ($didSkip) {
                $skipped++;
                continue;
            }

            $this->notifyDean($org);

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Notify the organization's Dean only. Officers get replaced, so the Dean
     * is the stable contact that must make sure the renewal is completed by
     * the adviser or the incoming officers.
     */
    private function notifyDean(Organization $org): void
    {
        $deans = $org->users()
            ->wherePivot('membership_status', 'Active')
            ->whereRaw('UPPER(organization_user.role) = ?', ['DEAN'])
            ->get();

        foreach ($deans as $dean) {
            if (filter_var(trim((string) $dean->email), FILTER_VALIDATE_EMAIL) === false) {
                Log::warning('Skipping invalid email for dean notification.', [
                    'organization_id' => $org->id,
                    'user_id' => $dean->id,
                ]);
                continue;
            }

            try {
                $dean->notify(new RenewalRequiredNotification($org));
            } catch (\Throwable $exception) {
                Log::warning('Skipping failed expired-renewal dean notification.', [
                    'organization_id' => $org->id,
                    'user_id' => $dean->id,
                    'reason' => $exception->getMessage(),
                ]);
            }
        }
    }
}
