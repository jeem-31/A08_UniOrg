<?php

namespace App\Mail;

use App\Models\ConfigurationToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConfigurationLinkEmail extends Mailable
{
    use Queueable, SerializesModels;

    public ConfigurationToken $configToken;
    public string $configurationUrl;
    public string $organizationName;
    public string $userName;
    public string $expirationTime;

    /**
     * Create a new message instance.
     */
    public function __construct(ConfigurationToken $configToken)
    {
        $this->configToken = $configToken;
        $this->configurationUrl = route('organization.configure', ['token' => $configToken->token]);
        $this->organizationName = $configToken->organization->name;
        $this->userName = $configToken->user->first_name . ' ' . $configToken->user->last_name;
        $this->expirationTime = $configToken->expires_at->format('F j, Y \a\t g:i A');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Configure Your Organization System - ' . $this->organizationName,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.configuration-link',
            with: [
                'configurationUrl' => $this->configurationUrl,
                'organizationName' => $this->organizationName,
                'userName' => $this->userName,
                'expirationTime' => $this->expirationTime,
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
