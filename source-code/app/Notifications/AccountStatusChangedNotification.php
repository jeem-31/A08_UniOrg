<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a member their account was deactivated or reactivated by an adviser.
 * Deactivation revokes both Admin and Student View (the account can no longer
 * sign in); reactivation restores the previous access.
 */
class AccountStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Organization $organization,
        public bool $deactivated,
        public string $changedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->deactivated
                ? 'Your UniOrg account was deactivated'
                : 'Your UniOrg account was reactivated')
            ->view([
                'html' => 'emails.account-status',
                'text' => 'emails.account-status-text',
            ], $this->mailViewData($notifiable));
    }

    /**
     * Build the shared view data used by the account status email.
     */
    protected function mailViewData(object $notifiable): array
    {
        $settings = $this->organization->relationLoaded('settings')
            ? $this->organization->getRelation('settings')
            : $this->organization->settings()->first();

        $logoUrl = $settings?->logo_url;
        if (! $logoUrl || ! str_starts_with($logoUrl, 'http')) {
            $logoUrl = 'https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/branding/LSPU_LOGO.png';
        }

        return [
            'firstName' => $notifiable->first_name ?: ($notifiable->name ?? 'there'),
            'organization' => $this->organization,
            'deactivated' => $this->deactivated,
            'changedBy' => $this->changedBy,
            'logoUrl' => $logoUrl,
            'primaryColor' => $settings?->primary_color ?? '#8238B3',
            'accentColor' => $settings?->complementary_color ?? '#D7AC28',
            'dashboardUrl' => route('dashboard'),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->deactivated
                ? 'Your account was deactivated'
                : 'Your account was reactivated',
            'message' => ($this->deactivated
                    ? 'Your account has been deactivated in '
                    : 'Your account has been reactivated in ')
                .$this->organization->name.'.',
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
            'icon' => $this->deactivated ? 'ph-user-minus' : 'ph-user-circle',
            'color' => $this->deactivated ? 'red' : 'green',
            'url' => route('dashboard'),
        ];
    }
}
