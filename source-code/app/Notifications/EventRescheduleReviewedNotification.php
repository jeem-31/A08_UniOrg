<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventRescheduleReviewedNotification extends Notification
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
                ? "Your reschedule request for \"{$this->event->title}\" was approved by OSAS. You may now update the event details."
                : "Your reschedule request for \"{$this->event->title}\" was rejected by OSAS.",
            'title' => $isApproved ? 'Reschedule Request Approved' : 'Reschedule Request Rejected',
            'icon' => $isApproved ? 'ph-check-circle' : 'ph-x-circle',
            'color' => $isApproved ? 'green' : 'red',
            'url' => route('admin.events.index'),
        ];
    }
}
