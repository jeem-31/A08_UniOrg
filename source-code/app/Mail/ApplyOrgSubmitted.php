<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplyOrgSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public $organizationName;
    public $requesterName;
    public $requestType;
    public $applicationType;

    /**
     * Create a new message instance.
     */
    public function __construct($organizationName, $requesterName, $requestType = 'apply', $applicationType = 'apply')
    {
        $this->organizationName = $organizationName;
        $this->requesterName = $requesterName;
        $this->requestType = $requestType;
        $this->applicationType = $applicationType;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Organization Application Received - Under Review',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.apply-org-submitted',
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
