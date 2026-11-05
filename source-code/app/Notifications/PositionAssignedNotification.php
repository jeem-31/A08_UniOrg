<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PositionAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Organization $organization,
        public string $position,
        public string $assignedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You have been assigned as {$this->position}")
            ->view([
                'html' => 'emails.position-assigned',
                'text' => 'emails.position-assigned-text',
            ], $this->mailViewData($notifiable));
    }

    /**
     * Build the shared view data used by position assignment changes.
     */
    protected function mailViewData(object $notifiable, string $notificationType = 'assigned'): array
    {
        $settings = $this->organization->relationLoaded('settings')
            ? $this->organization->getRelation('settings')
            : $this->organization->settings()->first();

        // Use the public object URL so Gmail loads the logo remotely instead
        // of receiving it as an inline MIME attachment.
        $logoUrl = $settings?->logo_url;
        if (! $logoUrl || ! str_starts_with($logoUrl, 'http')) {
            $logoUrl = 'https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/branding/LSPU_LOGO.png';
        }

        $firstName = $notifiable->first_name ?: ($notifiable->name ?? 'there');

        return [
            'firstName' => $firstName,
            'organization' => $this->organization,
            'position' => $this->position,
            'assignedBy' => $this->assignedBy,
            'logoUrl' => $logoUrl,
            'primaryColor' => $settings?->primary_color ?? '#8238B3',
            'accentColor' => $settings?->complementary_color ?? '#D7AC28',
            'dashboardUrl' => route('dashboard'),
            'notificationType' => $notificationType,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'You have a new organization position',
            'message' => "You have been assigned as {$this->position} in {$this->organization->name}.",
            'position' => $this->position,
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
            'icon' => 'ph-shield-check',
            'color' => 'blue',
            'url' => route('dashboard'),
        ];
    }
}
