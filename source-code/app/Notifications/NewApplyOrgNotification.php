<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewApplyOrgNotification extends Notification
{
    use Queueable;

    public $applyOrg;
    public $type;

    /**
     * Create a new notification instance.
     */
    public function __construct($applyOrg, $type = 'apply')
    {
        $this->applyOrg = $applyOrg;
        $this->type = $type; // 'apply' or 'renew'
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'apply_org_id' => $this->applyOrg->id,
            'organization_name' => $this->applyOrg->organization_name,
            'requester_name' => $this->applyOrg->requester_first_name . ' ' . $this->applyOrg->requester_last_name,
            'type' => $this->type,
            'message' => $this->type === 'apply' 
                ? "New Recognition Request from {$this->applyOrg->organization_name}"
                : "New Renewal Request from {$this->applyOrg->organization_name}",
            'icon' => $this->type === 'apply' ? 'ph-certificate' : 'ph-arrow-clockwise',
            'color' => $this->type === 'apply' ? 'blue' : 'amber',
            'url' => route('osas.apply-org.show', $this->applyOrg->id),
        ];
    }
}
