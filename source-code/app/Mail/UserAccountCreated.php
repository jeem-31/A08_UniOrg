<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserAccountCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;
    public $organizationName;
    public $role;
    public $temporaryPassword;

    /**
     * Create a new message instance.
     */
    public function __construct($name, $organizationName, $role, $temporaryPassword)
    {
        $this->name = $name;
        $this->organizationName = $organizationName;
        $this->role = $role;
        $this->temporaryPassword = $temporaryPassword;
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
            subject: 'Your UniOrg Account has been created!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.user-account-created',
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
