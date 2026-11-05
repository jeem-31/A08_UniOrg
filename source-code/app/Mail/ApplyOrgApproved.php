<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplyOrgApproved extends Mailable
{
    use Queueable, SerializesModels;

    public $organizationName;
    public $studentId;
    public $password;
    public $requesterName;
    public $position;

    /**
     * Create a new message instance.
     */
    public function __construct($organizationName, $studentId, $password, $requesterName, $position)
    {
        $this->organizationName = $organizationName;
        $this->studentId = $studentId;
        $this->password = $password;
        $this->requesterName = $requesterName;
        $this->position = $position;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Application Approved - Welcome to UniOrg!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.apply-org-approved',
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
