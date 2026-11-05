<?php

namespace App\Console\Commands;

use App\Services\AccountManagement\StudentAccountExpiryRunner;
use Illuminate\Console\Command;

class ExpireStudentAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounts:expire-students';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate student accounts whose fixed validity window (expiry plus grace) has fully passed';

    /**
     * Execute the console command.
     */
    public function handle(StudentAccountExpiryRunner $runner): int
    {
        $result = $runner->run();

        $this->info("Deactivated {$result['deactivated']} student account(s) after their expiry window closed.");

        return self::SUCCESS;
    }
}
