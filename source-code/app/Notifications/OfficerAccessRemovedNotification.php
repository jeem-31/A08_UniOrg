<?php

namespace App\Notifications;

use App\Models\Election;
use App\Models\Organization;
use App\Traits\HasOrganizationTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfficerAccessRemovedNotification extends Notification implements ShouldQueue
{
    use Queueable, HasOrganizationTheme;

    public function __construct(
        public Organization $organization,
        public string $previousPosition,
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
            ->subject('Your admin access has been updated in ' . $this->organization->name)
            ->view(['html' => 'emails.election', 'text' => 'emails.election-text'], [
                'organizationName' => $theme['organization_name'],
                'organizationLogo' => $theme['logo_url'],
                'primaryColor' => $theme['primary_color'],
                'recipientName' => $notifiable->first_name,
                'title' => 'Your officer access has ended',
                'preheader' => 'You are now a Member. Student features remain available.',
                'intro' => 'The election results for ' . $electionTitle . ' have been approved by the adviser. Following the transition, your admin access for ' . $this->previousPosition . ' in ' . $this->organization->name . ' has been removed. You are now listed as Member.',
                'highlight' => $this->previousPosition . ' — ' . $this->organization->name,
                'details' => [
                    'Previous Position' => $this->previousPosition,
                    'New Role' => 'Member',
                    'Organization' => $this->organization->name,
                    'Election' => $electionTitle,
                ],
                'body' => 'You can continue to access student features such as announcements, events and elections. If you believe this is an error, please contact your organization adviser.',
                'actionLabel' => 'Go to Dashboard',
                'actionUrl' => route('dashboard'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'officer_access_removed',
            'organization_id' => $this->organization->id,
            'election_id' => $this->election?->id,
            'previous_position' => $this->previousPosition,
            'title' => 'Admin access removed',
            'message' => 'Your access as ' . $this->previousPosition . ' in ' . $this->organization->name . ' has ended. You are now a Member.',
            'icon' => 'ph-user-minus',
            'color' => 'gray',
            'url' => route('dashboard'),
        ];
    }
}
