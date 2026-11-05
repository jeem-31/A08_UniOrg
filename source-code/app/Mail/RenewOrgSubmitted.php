<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RenewOrgSubmitted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $organizationName;
    public $requesterName;
    public $requestType;
    public $applicationType;

    public function __construct($organizationName, $requesterName, $requestType = 'request', $applicationType = 'renew')
    {
        $this->organizationName = $organizationName;
        $this->requesterName = $requesterName;
        $this->requestType = $requestType;
        $this->applicationType = $applicationType;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Renewal Application Received - Under Review',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.apply-org-submitted',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
