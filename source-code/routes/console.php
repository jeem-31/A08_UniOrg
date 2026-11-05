<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Schedule automatic publishing of scheduled posts every minute
Schedule::command('posts:publish-scheduled')->everyMinute();

// Check if any elections have started and notify students
Schedule::command('elections:check-start')->everyFiveMinutes();

// Auto-publish election results when end_date passes (every 5 mins, keep delay as requested)
Schedule::command('elections:check-end')->everyFiveMinutes();

// Warm permission cache daily at 2:00 AM during off-peak hours
Schedule::command('permission:warm-cache')->dailyAt('02:00');

// Auto-retry failed document analyses every 5 minutes (catches SSL errors, timeouts, etc.)
Schedule::command('documents:retry-failed --limit=50')->everyFiveMinutes();

// Re-dispatch any pending analyses that lost their queue job (e.g. after server restart)
Schedule::command('documents:reconcile-pending --limit=100')->everyFiveMinutes();

// The command is idempotent, so a missed scheduler run is recovered on the
// following day while the generated cycle still begins on August 1.
Schedule::command('payments:ensure-annual-membership-fees')
    ->dailyAt('00:05')
    ->timezone(config('app.timezone'));

// Move approved organizations with expired accreditation into the Renewal queue (daily)
Schedule::command('renewals:generate-expired')->dailyAt('00:15');

// Notify Dean+Adviser one month before accreditation expires (based on OSAS Settings period)
Schedule::command('accreditation:notify-expiring')->dailyAt('07:00');

// Deactivate student accounts that have fully passed their fixed validity window (daily)
Schedule::command('accounts:expire-students')->dailyAt('00:20');

// Execute scheduled officer transitions when the adviser's chosen effective date arrives
Schedule::command('transitions:process-scheduled')->everyMinute();
