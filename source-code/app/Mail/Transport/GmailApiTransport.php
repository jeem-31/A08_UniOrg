<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class GmailApiTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $settings = config('services.google');

        if (empty($settings['client_id']) || empty($settings['client_secret']) || empty($settings['refresh_token'])) {
            throw new \RuntimeException('Google Gmail API credentials are not configured.');
        }

        // Ensure the message has a From header — queued mailables may not
        // have one set if the global from config was not applied yet.
        $original = $message->getOriginalMessage();
        if ($original instanceof Email && empty($original->getFrom())) {
            $fromAddress = config('mail.from.address', 'noreply@example.com');
            $fromName    = config('mail.from.name', 'UniOrg');
            $original->from(new Address($fromAddress, $fromName));
        }

        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'refresh_token' => $settings['refresh_token'],
            'grant_type'    => 'refresh_token',
        ])->throw()->json();

        $raw = rtrim(strtr(base64_encode($message->getMessage()->toString()), '+/', '-_'), '=');

        Http::withToken($tokenResponse['access_token'])
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', ['raw' => $raw])
            ->throw();
    }

    public function __toString(): string
    {
        return 'gmail_api';
    }
}
