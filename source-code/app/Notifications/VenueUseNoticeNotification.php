<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VenueUseNoticeNotification extends Notification
{
    use Queueable;

    public Event $event;
    public string $stage;
    public string $ownerAcronym;

    public function __construct(Event $event, string $stage, string $ownerAcronym)
    {
        $this->event = $event;
        $this->stage = $stage;
        $this->ownerAcronym = $ownerAcronym;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $orgName = $this->event->organization->org_acronym
            ?? $this->event->organization->name
            ?? 'Unknown Org';

        return [
            'type' => 'venue_use_notice',
            'event_id' => $this->event->id,
            'organization_id' => $this->event->organization_id,
            'event_title' => $this->event->title,
            'organization' => $orgName,
            'venue' => $this->event->venue,
            'owner_acronym' => $this->ownerAcronym,
            'stage' => $this->stage,
            'message' => $this->message($orgName),
            'title' => "Premise use: {$this->event->venue}",
            'icon' => 'ph-map-pin',
            'color' => $this->color(),
            // Land on the dean dashboard's Premises card (auto-scrolls via # anchor).
            'url' => route('dean.dashboard', [], false) . '#premises-notices',
        ];
    }

    private function message(string $orgName): string
    {
        $base = "\"{$this->event->title}\" ({$orgName}) will use {$this->event->venue} ({$this->ownerAcronym} premise)";

        return match ($this->stage) {
            'proposed' => $base . '. Proposed — waiting for Dean review. Advisory only.',
            'dean_approved' => $base . '. Approved by Dean — forwarded to OSAS. Advisory only.',
            'dean_rejected' => $base . '. Rejected by Dean — premise no longer needed. Advisory only.',
            'osas_approved' => $base . '. Approved by OSAS — confirmed use. Advisory only.',
            'osas_rejected' => $base . '. Rejected by OSAS — premise no longer needed. Advisory only.',
            default => $base . '. Status update. Advisory only.',
        };
    }

    private function color(): string
    {
        return match ($this->stage) {
            'osas_approved', 'dean_approved' => 'green',
            'dean_rejected', 'osas_rejected' => 'red',
            default => 'blue',
        };
    }
}
