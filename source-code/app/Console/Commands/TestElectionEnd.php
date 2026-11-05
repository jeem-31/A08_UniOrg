<?php

namespace App\Console\Commands;

use App\Models\Election;
use Illuminate\Console\Command;

class TestElectionEnd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elections:test-end {id : Election ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set election end_date to NOW for testing results notification';

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
            'end_date' => now(),
        ]);

        $this->info("✅ Election '{$election->title}' end_date set to NOW!");
        $this->info("Now you can publish results and send notifications.");
    }
}
