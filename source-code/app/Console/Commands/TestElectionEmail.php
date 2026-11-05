<?php

namespace App\Console\Commands;

use App\Models\Election;
use App\Models\User;
use App\Notifications\VotingNowOpenNotification;
use Illuminate\Console\Command;

class TestElectionEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elections:test-email {election_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test sending election email notification to your account';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $electionId = $this->argument('election_id');
        $election = Election::find($electionId);

        if (!$election) {
            $this->error("Election with ID {$electionId} not found.");
            return;
        }

        // Use the same recipient rule as production election notifications.
        // Keep this test command aligned with the production recipient rule.
        $orgIds = [$election->organization_id];
        $recipient = User::eligibleForElectionNotifications($orgIds)->first();

        if (!$recipient) {
            $this->error("No eligible student-facing recipient found for this election.");
            return;
        }

        $this->info("Sending test email to: {$recipient->email}");
        
        // Send notification
        $recipient->notify(new VotingNowOpenNotification($election));

        $this->info("✅ Test email sent successfully!");
        $this->info("Check your inbox: {$recipient->email}");
    }
}
