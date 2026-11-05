<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventSubmittedToOsas extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $event;
    public $logoUrl;

    public function __construct(Event $event)
    {
        $this->event = $event;
        $this->logoUrl = 'https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/branding/LSPU_LOGO.png';
        if (config('queue.default') !== 'sync') {
            $this->onConnection('redis');
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Event Proposal Submitted for OSAS Approval',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.event-submitted-to-osas',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
