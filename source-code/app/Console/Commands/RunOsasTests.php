<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RunOsasTests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:osas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run all OSAS Account Management Property and E2E Tests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting OSAS Account Management Validation Suite...');

        $suites = [
            'Property Tests' => 'tests/Property/AccountManagement',
            'Workflow Tests (E2E)' => 'tests/Feature/AccountManagement',
            'Accessibility Tests' => 'tests/Browser/AccountManagement',
            'PostgreSQL Integration' => 'tests/Integration/AccountManagement',
        ];

        $allPassed = true;

        foreach ($suites as $name => $path) {
            $this->info("Running {$name}...");
            
            $env = array_merge($_SERVER, $_ENV);
            unset($env['DB_CONNECTION'], $env['DB_DATABASE']);
            $process = new Process(['php', 'artisan', 'test', $path, '--stop-on-failure'], null, $env);
            $process->setTimeout(300); // Property tests can take a while
            $process->run();

            if ($process->isSuccessful()) {
                $this->info("✅ {$name} Passed!");
            } else {
                $this->error("❌ {$name} Failed:");
                $this->line($process->getOutput());
                $this->line($process->getErrorOutput());
                $allPassed = false;
                break; // Stop early
            }
        }

        if ($allPassed) {
            $this->info('🏆 All OSAS Checkpoints and Properties (Tasks 6, 7, 8) Passed Successfully!');
            return 0;
        }

        return 1;
    }
}
