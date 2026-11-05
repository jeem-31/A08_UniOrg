<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use App\Services\AccountManagement\StudentAccountValidity;
use Illuminate\Console\Command;

class RecomputeStudentExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounts:recompute-expiry {--dry-run : Report the planned expiry values without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill or recompute users.expires_at for student accounts from their stored year level';

    /**
     * Execute the console command.
     */
    public function handle(StudentAccountValidity $validity): int
    {
        $periodStart = Organization::currentAccreditationPeriod()?->started_at ?? null;

        $students = User::query()
            ->where('role', 'student')
            ->get();

        $updated = 0;

        foreach ($students as $student) {
            $expiresAt = $validity->expiresAtFor($student->year_level, null, $periodStart);

            if ($expiresAt !== null && $student->expires_at !== null && $student->expires_at->equalTo($expiresAt)) {
                continue;
            }

            $value = $expiresAt?->toDateTimeString() ?? 'null';

            if ($this->option('dry-run')) {
                $this->line("[dry-run] {$student->getKey()} {$student->full_name}: {$value}");
                continue;
            }

            $student->expires_at = $expiresAt;
            $student->save();
            $updated++;
        }

        $this->info("Recomputed expiry for {$updated} student account(s).");

        return self::SUCCESS;
    }
}
