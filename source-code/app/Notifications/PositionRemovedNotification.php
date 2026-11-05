<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class PositionRemovedNotification extends PositionAssignedNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your {$this->position} position was removed")
            ->view([
                'html' => 'emails.position-assigned',
                'text' => 'emails.position-assigned-text',
            ], $this->mailViewData($notifiable, 'removed'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Your organization position was removed',
            'message' => "You are no longer assigned as {$this->position} in {$this->organization->name}.",
            'position' => $this->position,
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
            'icon' => 'ph-user-minus',
            'color' => 'red',
            'url' => route('dashboard'),
        ];
    }
}
