<?php

namespace App\Console\Commands;

use App\Models\ApplyOrg;
use App\Models\DocumentLibraryEntry;
use App\Services\DocumentLibrary\AccreditationYear;
use App\Services\DocumentLibrary\CurrentLibraryOutputScope;
use Illuminate\Console\Command;

final class BackfillAccreditationYears extends Command
{
    private const CHUNK_SIZE = 200;

    protected $signature = 'library:backfill-accreditation-years';

    protected $description = 'Backfill accreditation years for approved applications from their current document analyses';

    public function handle(CurrentLibraryOutputScope $currentOutput): int
    {
        $updated = 0;
        $withoutYear = 0;

        ApplyOrg::query()
            ->where('status', 'Approved')
            ->whereNull('accreditation_year')
            ->chunkById(self::CHUNK_SIZE, function ($applications) use (
                $currentOutput,
                &$updated,
                &$withoutYear,
            ): void {
                foreach ($applications as $application) {
                    $year = $this->yearFromCurrentOutput($currentOutput, (int) $application->id);

                    if ($year === null) {
                        $withoutYear++;
                        continue;
                    }

                    $application->update(['accreditation_year' => $year]);
                    $updated++;
                }
            });

        $this->info(
            "Backfilled accreditation years for {$updated} application(s); "
            ."{$withoutYear} left without a determinable year."
        );

        return self::SUCCESS;
    }

    private function yearFromCurrentOutput(CurrentLibraryOutputScope $scope, int $applicationId): ?string
    {
        $entries = DocumentLibraryEntry::query()
            ->where('document_library_entries.apply_org_id', $applicationId);
        $scope->apply($entries);

        $tally = [];
        foreach ($entries->get() as $entry) {
            if (! in_array($entry->getAttribute('has_current_output'), [1, '1', true], true)) {
                continue;
            }

            $information = $entry->getAttribute('key_information');
            if (is_string($information)) {
                $information = json_decode($information, true);
            }

            $year = is_array($information) && is_string($information['school_year'] ?? null)
                ? AccreditationYear::parse($information['school_year'])
                : null;

            if ($year === null) {
                continue;
            }

            $tally[$year->key()] = ($tally[$year->key()] ?? 0) + 1;
        }

        if ($tally === []) {
            return null;
        }

        uksort($tally, static function (string $left, string $right) use ($tally): int {
            return $tally[$right] <=> $tally[$left] ?: strcmp($left, $right);
        });

        return array_key_first($tally);
    }
}
