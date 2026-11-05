<?php

namespace App\Console\Commands;

use App\Models\Election;
use App\Models\User;
use App\Notifications\VotingNowOpenNotification;
use Illuminate\Console\Command;

class TestElectionStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elections:test-start {id : Election ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set election start_date to NOW and send voting notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $electionId = $this->argument('id');
        $election = Election::find($electionId);

        if (!$election) {
            $this->error("Election with ID {$electionId} not found.");
            return;
        }

        $election->update([
            'start_date' => now(),
            'start_notified' => false, // Reset so notification will be sent
        ]);

        $this->info("✅ Election '{$election->title}' start_date set to NOW!");
        
        // Notify eligible student-facing organization members, including officer admins.
        // Keep this test command aligned with the production recipient rule.
        $orgIds = [$election->organization_id];
        $recipients = User::eligibleForElectionNotifications($orgIds)->get();
        
        if ($recipients->isEmpty()) {
            $this->warn("No eligible student-facing recipients found to notify.");
            return;
        }

        $this->info("Sending notifications to {$recipients->count()} eligible recipients...");
        $sent = 0;
        foreach ($recipients as $recipient) {
            if (filter_var(trim((string) $recipient->email), FILTER_VALIDATE_EMAIL) === false) {
                $this->warn("Skipping invalid email for user {$recipient->id}.");
                continue;
            }

            $recipient->notify(new VotingNowOpenNotification($election));
            $sent++;
        }
        
        $election->update(['start_notified' => true]);

        $this->info("✅ Sent {$sent} of {$recipients->count()} voting notifications successfully!");
        $this->info("Check your email inbox shortly.");
    }
}
