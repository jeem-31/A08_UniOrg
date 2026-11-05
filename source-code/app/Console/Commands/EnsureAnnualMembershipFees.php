<?php

namespace App\Console\Commands;

use App\Events\PaymentCreated;
use App\Services\MembershipFeeService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class EnsureAnnualMembershipFees extends Command
{
    protected $signature = 'payments:ensure-annual-membership-fees {--date= : ISO date used to select the annual cycle}';

    protected $description = 'Ensure every active Mother Organization has its protected annual Membership Fee';

    public function handle(MembershipFeeService $membershipFees): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'), config('app.timezone'))
            : null;
        $created = 0;

        foreach ($membershipFees->ensureForActiveMotherOrganizations($date) as $fee) {
            if (! $fee->wasRecentlyCreated) {
                continue;
            }

            $created++;

            try {
                broadcast(new PaymentCreated($fee));
            } catch (Throwable $exception) {
                report($exception);
                $this->warn("Membership Fee #{$fee->id} was created, but its realtime update could not be sent.");
            }
        }

        $this->info("Ensured annual Membership Fees; {$created} new record(s) created.");

        return self::SUCCESS;
    }
}
