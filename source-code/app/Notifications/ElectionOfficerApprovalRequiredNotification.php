<?php

namespace App\Notifications;

use App\Models\Election;
use App\Traits\HasOrganizationTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ElectionOfficerApprovalRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable, HasOrganizationTheme;

    protected Election $election;

    public function __construct(Election $election)
    {
        $this->election = $election;
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
        $theme = $this->getOrganizationTheme($notifiable, $this->election->organization);
        $winners = $this->election->candidates()
            ->orderByDesc('vote_count')
            ->get()
            ->groupBy('position')
            ->map(fn ($c) => $c->first());

        $details = [
            'Election' => $this->election->title,
            'Winners determined' => $winners->count() . ' positions',
            'Election ended' => $this->election->end_date->format('F j, Y g:i A'),
        ];

        return (new MailMessage)
            ->subject('Action required: Approve new officers for ' . $this->election->title)
            ->view(['html' => 'emails.election', 'text' => 'emails.election-text'], [
                'organizationName' => $theme['organization_name'],
                'organizationLogo' => $theme['logo_url'],
                'primaryColor' => $theme['primary_color'],
                'recipientName' => $notifiable->first_name,
                'title' => 'New officers awaiting approval',
                'preheader' => 'Review and approve the new officers in Settings → Admins.',
                'intro' => 'The election has concluded and winners have been determined. As adviser, please review the results and approve the officer transition. You may set an effective date for automatic activation.',
                'highlight' => $this->election->title,
                'details' => $details,
                'body' => 'Approved officers will gain access to the admin side and will be notified to switch via their profile dropdown.',
                'actionLabel' => 'Review in Settings',
                'actionUrl' => route('admin.settings.index', ['tab' => 'admins']),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'election_officer_approval_required',
            'election_id' => $this->election->id,
            'organization_id' => $this->election->organization_id,
            'title' => 'Officer transition awaiting approval',
            'message' => $this->election->title . ' — approve new officers in Settings → Admins.',
            'icon' => 'ph-shield-check',
            'color' => 'amber',
            'url' => route('admin.settings.index', ['tab' => 'admins']),
        ];
    }
}
