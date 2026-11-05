<?php

namespace App\Notifications;

use App\Models\AdminChat;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOsasChatNotification extends Notification
{
    use Queueable;

    public AdminChat $chat;

    public bool $fromOsas;

    /**
     * Create a new notification instance.
     */
    public function __construct(AdminChat $chat, bool $fromOsas = false)
    {
        $this->chat = $chat;
        $this->fromOsas = $fromOsas;
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
        $orgName = $this->chat->organization?->org_acronym ?? $this->chat->organization?->name ?? 'Organization';
        $senderName = $this->chat->sender?->full_name ?? 'Organization admin';

        if ($this->fromOsas) {
            $message = "New message from OSAS: " . \Illuminate\Support\Str::limit($this->chat->message, 50);
            $url = route('admin.chat.index');
        } else {
            $message = "New message from {$orgName}: " . \Illuminate\Support\Str::limit($this->chat->message, 50);
            $url = route('osas.dashboard');
        }

        return [
            'chat_id' => $this->chat->id,
            'organization_id' => $this->chat->organization_id,
            'organization_name' => $orgName,
            'sender_name' => $senderName,
            'message' => $message,
            'icon' => 'ph-chats',
            'color' => 'blue',
            'url' => $url,
        ];
    }
}
