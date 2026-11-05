<?php

namespace App\Console\Commands;

use App\Services\ExpiringAccreditationNotifier;
use Illuminate\Console\Command;

class NotifyExpiringAccreditations extends Command
{
    protected $signature = 'accreditation:notify-expiring';

    protected $description = 'Notify Dean+Adviser when accreditation will expire within 30 days (based on OSAS Settings accreditation period)';

    public function handle(ExpiringAccreditationNotifier $notifier): int
    {
        $result = $notifier->notify();
        $this->info("Notified {$result['notified']} organization(s) expiring within 30 days. {$result['skipped']} skipped (already notified or no recipient).");

        return self::SUCCESS;
    }
}
