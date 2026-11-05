<?php

namespace App\Console\Commands;

use App\Models\Election;
use App\Models\User;
use App\Notifications\ElectionResultsPublishedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckElectionEnd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elections:check-end';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if any elections have ended and auto-publish results';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get elections that have ended but haven't published results yet
        $endedElections = Election::with('organization')
            ->whereRaw('is_posted = TRUE')
            ->whereRaw('results_published = FALSE')
            ->where('end_date', '<=', now())
            ->get();

        if ($endedElections->isEmpty()) {
            $this->info('No elections have ended recently.');
            return;
        }

        foreach ($endedElections as $election) {
            $election->results_published = true;
            $election->is_active = false;
            $election->save();

            // Broadcast real-time update (safe - failure does not rollback DB)
            safe_broadcast(new \App\Events\ElectionUpdated($election));

            // Send results notifications - private to owning org only (same as manual publishResults)
            $orgIds = [$election->organization_id];
            $students = User::eligibleForElectionNotifications($orgIds)->get();

            $invalidRecipients = $students->filter(function (User $student): bool {
                return filter_var(trim((string) $student->email), FILTER_VALIDATE_EMAIL) === false;
            });

            if ($invalidRecipients->isNotEmpty()) {
                Log::warning('Skipping users with invalid email addresses while auto-publishing election results.', [
                    'election_id' => $election->id,
                    'user_ids' => $invalidRecipients->pluck('id')->values()->all(),
                ]);
            }

            $students = $students->reject(function (User $student): bool {
                return filter_var(trim((string) $student->email), FILTER_VALIDATE_EMAIL) === false;
            })->values();

            $sent = 0;
            $failed = 0;
            foreach ($students as $student) {
                try {
                    $student->notify(new ElectionResultsPublishedNotification($election));
                    $sent++;
                } catch (\Throwable $exception) {
                    $failed++;
                    Log::warning('Skipping failed auto-publish election results email.', [
                        'election_id' => $election->id,
                        'user_id' => $student->id,
                        'reason' => $exception->getMessage(),
                    ]);
                }
            }

            if ($failed > 0) {
                Log::warning('Some auto-published election results emails were skipped.', [
                    'election_id' => $election->id,
                    'failed_count' => $failed,
                ]);
            }

            $this->info("Auto-published results for: {$election->title}. Sent {$sent} of {$students->count()} notifications. Failed/skipped: {$failed}.");
        }

        $this->info('Election end auto-publish processed successfully!');
    }
}
