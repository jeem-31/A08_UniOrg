<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventDeanActionNotification extends Notification
{
    use Queueable;

    public Event $event;
    public string $action;
    public ?string $rejectionReason;

    public function __construct(Event $event, string $action, ?string $rejectionReason = null)
    {
        $this->event = $event;
        $this->action = $action;
        $this->rejectionReason = $rejectionReason;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $isApproved = $this->action === 'approved';

        return [
            'event_id' => $this->event->id,
            'organization_id' => $this->event->organization_id,
            'event_title' => $this->event->title,
            'action' => $this->action,
            'rejection_reason' => $this->rejectionReason,
            'message' => $isApproved
                ? "Event \"{$this->event->title}\" has been approved by the Dean."
                : "Event \"{$this->event->title}\" has been rejected by the Dean.",
            'title' => $isApproved ? 'Event Approved by Dean' : 'Event Rejected by Dean',
            'icon' => $isApproved ? 'ph-check-circle' : 'ph-x-circle',
            'color' => $isApproved ? 'green' : 'red',
            'url' => route('admin.events.index'),
        ];
    }
}
