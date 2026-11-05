<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Traits\HasOrganizationTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RenewalRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable, HasOrganizationTheme;

    protected Organization $organization;

    public function __construct(Organization $organization)
    {
        $this->organization = $organization;
        if (config('queue.default') !== 'sync') {
            $this->onConnection('redis');
            $this->onQueue('emails');
        }
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->getOrganizationTheme($notifiable, $this->organization);
        $expiredOn = $this->organization->accreditation_expires_at?->toDateString() ?? 'the recorded expiry date';

        return (new MailMessage)
            ->subject('Accreditation expired – renewal required: '.$t['organization_name'])
            ->view(
                ['html' => 'emails.election', 'text' => 'emails.election-text'],
                [
                    'organizationName' => $t['organization_name'],
                    'organizationLogo' => 'https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/public_image/LSPU_LOGO.png',
                    'primaryColor' => $t['primary_color'],
                    'recipientName' => $notifiable->first_name,
                    'title' => 'Accreditation expired – renewal required',
                    'preheader' => "{$t['organization_name']} must be renewed. Please coordinate with the adviser or incoming officers.",
                    'intro' => "The accreditation of {$t['organization_name']} expired on {$expiredOn}. The organization has been moved to the renewal queue and must re-upload its renewal documents to continue operating.",
                    'highlight' => $t['organization_name'],
                    'details' => [
                        'Accreditation expired' => $expiredOn,
                        'Action needed' => 'Complete the renewal (upload documents)',
                        'Who should do it' => 'The organization adviser or the incoming officers who will replace the current officers',
                    ],
                    'body' => 'Please coordinate with the organization adviser or its incoming officers so the renewal is submitted. Until the renewal is approved, the organization cannot be considered accredited.',
                    'actionLabel' => 'Go to renewal',
                    'actionUrl' => route('renew.index'),
                ],
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'renewal_required',
            'organization_id' => $this->organization->id,
            'title' => 'Accreditation expired – renewal required',
            'message' => $this->organization->name.' accreditation expired. Please coordinate with the adviser or incoming officers to complete the renewal.',
            'icon' => 'fa-certificate',
            'color' => 'amber',
            'url' => route('renew.index'),
            'expires_at' => $this->organization->accreditation_expires_at?->toDateString(),
        ];
    }
}
