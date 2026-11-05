<?php

namespace App\Console\Commands;

use App\Services\ExpiredRenewalGenerator;
use Illuminate\Console\Command;

class GenerateExpiredRenewals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'renewals:generate-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate pending renewal records for approved organizations whose accreditation has expired and notify their Dean';

    /**
     * Execute the console command.
     */
    public function handle(ExpiredRenewalGenerator $generator)
    {
        $result = $generator->generate();

        $this->info("Generated {$result['created']} renewal record(s) for expired organizations. {$result['skipped']} organization(s) already had a pending renewal.");

        return self::SUCCESS;
    }
}
