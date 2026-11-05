<?php

namespace App\Notifications;

use App\Models\Election;
use App\Models\Organization;
use App\Traits\HasOrganizationTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfficerAccessApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable, HasOrganizationTheme;

    public function __construct(
        public Organization $organization,
        public string $position,
        public ?Election $election = null,
    ) {
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
        $theme = $this->getOrganizationTheme($notifiable, $this->organization);
        $electionTitle = $this->election?->title ?? 'Election';

        return (new MailMessage)
            ->subject('You now have admin access as ' . $this->position)
            ->view(['html' => 'emails.election', 'text' => 'emails.election-text'], [
                'organizationName' => $theme['organization_name'],
                'organizationLogo' => $theme['logo_url'],
                'primaryColor' => $theme['primary_color'],
                'recipientName' => $notifiable->first_name,
                'title' => 'You have been approved as ' . $this->position,
                'preheader' => 'Switch to Admin View from your profile dropdown.',
                'intro' => 'Congratulations! Your election win has been approved by the adviser. You now have access to the admin side of ' . $this->organization->name . '.',
                'highlight' => $this->position . ' — ' . $this->organization->name,
                'details' => [
                    'Position' => $this->position,
                    'Organization' => $this->organization->name,
                    'Election' => $electionTitle,
                ],
                'body' => 'To access admin features, click your profile picture in the top-right corner and select “Switch to Admin View”.',
                'actionLabel' => 'Go to Dashboard',
                'actionUrl' => route('dashboard'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'officer_access_approved',
            'organization_id' => $this->organization->id,
            'election_id' => $this->election?->id,
            'position' => $this->position,
            'title' => 'Admin access approved',
            'message' => 'You are now ' . $this->position . ' in ' . $this->organization->name . '. Switch to Admin View from your profile dropdown.',
            'icon' => 'ph-shield-check',
            'color' => 'green',
            'url' => route('dashboard'),
        ];
    }
}
