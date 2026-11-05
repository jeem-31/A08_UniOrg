<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventSubmittedToOsasNotification extends Notification
{
    use Queueable;

    public Event $event;

    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $orgName = $this->event->organization->org_acronym ?? $this->event->organization->name ?? 'Unknown Org';

        return [
            'event_id' => $this->event->id,
            'organization_id' => $this->event->organization_id,
            'event_title' => $this->event->title,
            'organization' => $orgName,
            'message' => "\"{$this->event->title}\" has been submitted by {$orgName} for OSAS approval.",
            'title' => 'New Event Submission',
            'icon' => 'ph-paper-plane-tilt',
            'color' => 'blue',
            'url' => route('osas.proposals.index', [], false),
        ];
    }
}
