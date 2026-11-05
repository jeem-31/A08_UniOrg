<?php

use App\Models\ApplyOrg;
use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;

/**
 * Stamp report-linker rows (created before school-year linkers existed)
 * with the current school year so their library entries leave the
 * "Unspecified Year" folder. Only NULL-year linkers are touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $linkers = ApplyOrg::query()
            ->where('request_type', 'request')
            ->whereNull('application_type')
            ->whereNull('accreditation_year')
            ->where('admin_notes', 'like', '[report-linker]%')
            ->orderBy('id')
            ->get();

        foreach ($linkers as $linker) {
            $organization = $linker->organization;
            $year = $this->schoolYearFor($linker, $organization);
            if ($year === null) {
                continue;
            }

            $linker->forceFill(['accreditation_year' => $year])->save();
        }
    }

    public function down(): void
    {
        // No-op: previously-NULL years cannot be distinguished from
        // intentionally stamped ones after the fact.
    }

    private function schoolYearFor(ApplyOrg $linker, ?Organization $organization): ?string
    {
        $period = Organization::currentAccreditationPeriod();
        if ($period && $period->started_at && $period->expires_at) {
            $schoolYear = Organization::accreditationSchoolYear($period->started_at, $period->expires_at);
            if (is_string($schoolYear) && $schoolYear !== '') {
                return $schoolYear;
            }
        }

        if ($organization && $organization->accreditation_started_at && $organization->accreditation_expires_at) {
            $schoolYear = Organization::accreditationSchoolYear(
                $organization->accreditation_started_at,
                $organization->accreditation_expires_at,
            );
            if (is_string($schoolYear) && $schoolYear !== '') {
                return $schoolYear;
            }
        }

        if ($organization && $organization->department_id) {
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
};
