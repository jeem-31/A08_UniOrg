<?php

namespace App\Console\Commands;

use App\Models\ElectionOfficerTransition;
use App\Services\ElectionOfficerTransitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledOfficerTransitions extends Command
{
    protected $signature = 'transitions:process-scheduled';

    protected $description = 'Execute scheduled officer transitions whose effective date has arrived';

    public function handle(ElectionOfficerTransitionService $service): int
    {
        $transitions = ElectionOfficerTransition::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->with('election')
            ->get();

        if ($transitions->isEmpty()) {
            $this->info('No scheduled transitions to process.');
            return 0;
        }

        foreach ($transitions as $transition) {
            try {
                $service->execute($transition);
                $this->info("Completed transition for election {$transition->election_id}.");
            } catch (\Throwable $exception) {
                Log::error('Failed to execute scheduled officer transition.', [
                    'transition_id' => $transition->id,
                    'election_id' => $transition->election_id,
                    'reason' => $exception->getMessage(),
                ]);
                $this->error("Failed transition {$transition->id}: {$exception->getMessage()}");
            }
        }

        return 0;
    }
}
