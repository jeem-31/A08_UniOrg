<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\ApplyOrgSubmitted;
use App\Mail\ApplyOrgApproved;
use App\Mail\ApplyOrgRejected;

class TestEmailNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:emails {email : Explicit test recipient} {--type=all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email notifications for system requests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $type = $this->option('type');

        $this->info("🚀 Testing email notifications...");
        $this->info("📧 Sending to: {$email}");
        $this->newLine();

        // Test data
        $organizationName = "BSIT Society Test Organization";
        $requesterName = "Demo Requester";
        $studentId = "2024-TEST";
        $password = \Illuminate\Support\Str::random(32);
        $position = "Organization President";
        $rejectionReason = "This is a test rejection. Some required documents are missing or incomplete. Please review and resubmit with all necessary files.";

        try {
            // Test 1: Application Submitted Email
            if ($type === 'all' || $type === 'submitted') {
                $this->info("📤 Sending 'Application Submitted' email...");
                Mail::to($email)->send(
                    new ApplyOrgSubmitted(
                        $organizationName,
                        $requesterName,
                        'apply',
                        'apply'
                    )
                );
                $this->line("   ✅ Application Submitted email sent!");
                $this->newLine();
            }

            // Test 2: Application Approved Email
            if ($type === 'all' || $type === 'approved') {
                $this->info("📤 Sending 'Application Approved' email...");
                Mail::to($email)->send(
                    new ApplyOrgApproved(
                        $organizationName,
                        $studentId,
                        $password,
                        $requesterName,
                        $position
                    )
                );
                $this->line("   ✅ Application Approved email sent!");
                $this->newLine();
            }

            // Test 3: Application Rejected Email
            if ($type === 'all' || $type === 'rejected') {
                $this->info("📤 Sending 'Application Rejected' email...");
                Mail::to($email)->send(
                    new ApplyOrgRejected(
                        $organizationName,
                        $requesterName,
                        $rejectionReason
                    )
                );
                $this->line("   ✅ Application Rejected email sent!");
                $this->newLine();
            }

            $this->newLine();
            $this->info("🎉 All test emails sent successfully!");
            $this->info("📬 Check your inbox at: {$email}");
            $this->newLine();
            $this->warn("⚠️  Note: Emails may take a few minutes to arrive.");
            $this->warn("⚠️  Check your spam folder if you don't see them.");

        } catch (\Exception $e) {
            $this->error("❌ Error sending emails: " . $e->getMessage());
            $this->newLine();
            $this->error("Stack trace:");
            $this->line($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
