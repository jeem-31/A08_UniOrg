<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationApplicationApproved extends Mailable
{
    use Queueable, SerializesModels;

    public $organizationName;
    public $requesterName;
    public $applicationType;

    /**
     * Create a new message instance.
     */
    public function __construct($organizationName, $requesterName, $applicationType = 'apply')
    {
        $this->organizationName = $organizationName;
        $this->requesterName = $requesterName;
        $this->applicationType = $applicationType;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Organization Application Approved - Create Your System',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.organization-application-approved',
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
