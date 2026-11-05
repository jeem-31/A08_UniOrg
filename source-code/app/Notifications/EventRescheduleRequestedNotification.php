<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventRescheduleRequestedNotification extends Notification
{
    use Queueable;

    public Event $event;
    public string $reason;

    public function __construct(Event $event, string $reason)
    {
        $this->event = $event;
        $this->reason = $reason;
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
            'reason' => $this->reason,
            'message' => "{$orgName} requested to reschedule \"{$this->event->title}\".",
            'title' => 'Event Reschedule Requested',
            'icon' => 'ph-calendar-blank',
            'color' => 'amber',
            'url' => route('osas.proposals.index', [], false),
        ];
    }
}
