<?php

namespace App\Console\Commands;

use App\Models\Election;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\VotingNowOpenNotification;
use Illuminate\Console\Command;

class CheckElectionStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elections:check-start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if any elections have started and notify students';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get elections that have started but haven't been notified yet
        $startedElections = Election::with('organization')
            ->whereRaw('is_posted = TRUE')
            ->whereRaw('is_active = TRUE')
            ->where('start_date', '<=', now())
            ->whereRaw('start_notified = FALSE')
            ->get();

        if ($startedElections->isEmpty()) {
            $this->info('No elections have started recently.');
            return;
        }

        foreach ($startedElections as $election) {
            // Get student-facing recipients: regular students and officer admins,
            // excluding adviser, dean, and OSAS-only accounts.
            // Notify only active members of the organization that owns this election.
            $orgIds = [$election->organization_id];
            $recipients = User::eligibleForElectionNotifications($orgIds)->get();

            $sent = 0;
            $failed = 0;
            foreach ($recipients as $recipient) {
                if (filter_var(trim((string) $recipient->email), FILTER_VALIDATE_EMAIL) === false) {
                    $failed++;
                    $this->warn("Skipping invalid email for user {$recipient->id}.");
                    continue;
                }

                try {
                    $recipient->notify(new VotingNowOpenNotification($election));
                    $sent++;
                } catch (\Throwable $exception) {
                    $failed++;
                    \Log::warning('Skipping failed scheduled voting-open email.', [
                        'election_id' => $election->id,
                        'user_id' => $recipient->id,
                        'reason' => $exception->getMessage(),
                    ]);
                }
            }
            
            // Mark as notified
            $election->update(['start_notified' => true]);
            
            $this->info("Sent {$sent} of {$recipients->count()} voting notifications for: {$election->title}. Failed/skipped: {$failed}.");
        }

        $this->info('Election start notifications processed successfully!');
    }
}
