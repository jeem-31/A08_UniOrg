<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Traits\HasOrganizationTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccreditationExpiringSoonNotification extends Notification implements ShouldQueue
{
    use Queueable, HasOrganizationTheme;

    protected Organization $organization;

    protected int $daysLeft;

    public function __construct(Organization $organization, int $daysLeft)
    {
        $this->organization = $organization;
        $this->daysLeft = $daysLeft;
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
        $expiresOn = $this->organization->accreditation_expires_at?->toDateString() ?? 'the recorded expiry date';
        $daysText = $this->daysLeft === 1 ? '1 day' : $this->daysLeft.' days';

        return (new MailMessage)
            ->subject('Accreditation expiring in '.$daysText.' – renewal required: '.$t['organization_name'])
            ->view(
                ['html' => 'emails.election', 'text' => 'emails.election-text'],
                [
                    'organizationName' => $t['organization_name'],
                    'organizationLogo' => 'https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/public_image/LSPU_LOGO.png',
                    'primaryColor' => $t['primary_color'],
                    'recipientName' => $notifiable->first_name,
                    'title' => 'Accreditation expiring in '.$daysText,
                    'preheader' => "{$t['organization_name']} accreditation expires on {$expiresOn}. Please prepare the renewal.",
                    'intro' => "The accreditation of {$t['organization_name']} will expire on {$expiresOn} (in {$daysText}). Please coordinate with the organization adviser and incoming officers to prepare the renewal documents.",
                    'highlight' => $t['organization_name'],
                    'details' => [
                        'Expires on' => $expiresOn,
                        'Days remaining' => $daysText,
                        'Action needed' => 'Prepare renewal documents (one month before expiry, renewal will be required)',
                    ],
                    'body' => 'You are receiving this early notice because the accreditation is expiring soon. Renewal can only be submitted after the current accreditation has expired.',
                    'actionLabel' => 'View accreditation',
                    'actionUrl' => route('admin.renew.index'),
                ],
            );
    }

    public function toArray(object $notifiable): array
    {
        $daysText = $this->daysLeft === 1 ? '1 day' : $this->daysLeft.' days';

        return [
            'type' => 'accreditation_expiring',
            'organization_id' => $this->organization->id,
            'title' => 'Accreditation expiring in '.$daysText,
            'message' => $this->organization->name.' accreditation expires in '.$daysText.' ('.$this->organization->accreditation_expires_at?->toDateString().'). Please prepare the renewal.',
            'icon' => 'ph-hourglass',
            'color' => 'amber',
            'url' => route('admin.renew.index'),
            'expires_at' => $this->organization->accreditation_expires_at?->toDateString(),
            'days_left' => $this->daysLeft,
        ];
    }
}
