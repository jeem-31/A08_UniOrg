<?php

namespace App\Jobs;

use App\Mail\ConfigurationLinkEmail;
use App\Models\ConfigurationToken;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendConfigurationLinkEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $userEmail,
        public int $tokenId
    ) {
        // Keep email delivery off the web request and route it through Redis.
        if (config('queue.default') !== 'sync') {
            $this->onConnection('redis');
            $this->onQueue('emails');
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Retrieve the configuration token
            $configToken = ConfigurationToken::find($this->tokenId);
            
            if (!$configToken) {
                Log::error('Configuration token not found: ' . $this->tokenId);
                return;
            }

            // Send the email
            Mail::to($this->userEmail)->send(
                new ConfigurationLinkEmail($configToken)
            );

            Log::info('Configuration link email sent successfully to: ' . $this->userEmail);
        } catch (\Exception $e) {
            Log::error('Failed to send configuration link email: ' . $e->getMessage());
            throw $e; // Re-throw to allow job retry
        }
    }
}
