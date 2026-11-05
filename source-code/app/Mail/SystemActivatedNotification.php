<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class SystemActivatedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Organization $organization;
    public User $configuredBy;
    public array $enabledModules;
    public string $activationTimestamp;

    /**
     * Create a new message instance.
     */
    public function __construct(Organization $organization, User $configuredBy, array $enabledModules)
    {
        $this->organization = $organization;
        $this->configuredBy = $configuredBy;
        $this->enabledModules = $enabledModules;
        $this->activationTimestamp = now()->format('F j, Y \a\t g:i A');
        if (config('queue.default') !== 'sync') {
            $this->onConnection('redis');
            $this->onQueue('emails');
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Organization System Activated - ' . $this->organization->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.system-activated',
            with: [
                'organizationName' => $this->organization->name,
                'organizationAcronym' => $this->organization->org_acronym,
                'organizationType' => $this->organization->type,
                'configuredByName' => $this->configuredBy->name,
                'configuredByEmail' => $this->configuredBy->email,
                'enabledModules' => $this->enabledModules,
                'activationTimestamp' => $this->activationTimestamp,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
