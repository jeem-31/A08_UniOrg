<?php

namespace App\Notifications;

use App\Traits\HasOrganizationTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class EdushareNotification extends Notification implements ShouldQueue
{
    use Queueable, HasOrganizationTheme;

    public $item;
    public $folder;
    public $sharedBy;
    public $type;

    /**
     * Create a new notification instance.
     * @param $item EdushareItem|null
     * @param $folder EdushareFolder|null
     * @param $sharedBy string - The name of the person sharing
     * @param $type string - 'item' or 'folder'
     */
    public function __construct($item = null, $folder = null, $sharedBy = null, $type = 'item')
    {
        $this->item = $item;
        $this->folder = $folder;
        $this->sharedBy = $sharedBy; // Now this is a string (name)
        $this->type = $type;
        if (config('queue.default') !== 'sync') {
            $this->onConnection('redis');
            $this->onQueue('emails');
        }
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        $theme = $this->getOrganizationTheme($notifiable);
        $sharerName = $this->sharedBy ?: 'Someone';
        
        if ($this->type === 'folder') {
            $folderName = $this->folder ? $this->folder->name : 'a folder';
            
            $message = (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('📁 New Folder Shared with You - UniOrg EduShare')
                ->greeting('Hello ' . $notifiable->first_name . '!')
                ->line('Great news! **' . $sharerName . '** has shared a folder with you on **EduShare**.')
                ->line('---')
                ->line('📁 **Folder Details:**')
                ->line('')
                ->line('**Folder Name:** ' . $folderName)
                ->line('**Shared By:** ' . $sharerName)
                ->line('**Platform:** UniOrg EduShare')
                ->line('---')
                ->line('**What You Can Do:**')
                ->line('• View all files inside the folder')
                ->line('• Download files you need')
                ->line('• Access anytime from your EduShare account')
                ->line('---')
                ->line('**How to Access:**')
                ->line('1. Login to your UniOrg account')
                ->line('2. Go to **EduShare** section')
                ->line('3. Click on **"Shared with Me"** tab')
                ->line('4. Find the folder: **' . $folderName . '**')
                ->line('---')
                ->line('This folder has been shared to help you with your studies. Make the most of these resources!')
                ->salutation('Best regards,  
The UniOrg Team')
                ->theme('default');

            $message->viewData = [
                'primaryColor' => $theme['primary_color'],
                'complementaryColor' => $theme['complementary_color'],
                'organizationName' => 'UniOrg',
                'organizationLogo' => 'https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/public_image/LSPU_LOGO.png',
            ];

            return $message;
        }
        
        $itemTitle = $this->item ? $this->item->title : 'a file';
        $fileType = $this->item ? strtoupper($this->item->file_type) : 'FILE';
        
        $message = (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('New File Shared with You - UniOrg EduShare')
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('Great news! **' . $sharerName . '** has shared a file with you on **EduShare**.')
            ->line('---')
            ->line('**File Details:**')
            ->line('')
            ->line('**File Name:** ' . $itemTitle)
            ->line('**File Type:** ' . $fileType)
            ->line('**Shared By:** ' . $sharerName)
            ->line('**Platform:** UniOrg EduShare')
            ->line('---')
            ->line('**What You Can Do:**')
            ->line('• View the file online')
            ->line('• Download for offline access')
            ->line('• Share with other students (if allowed)')
            ->line('---')
            ->line('**How to Access:**')
            ->line('1. Login to your UniOrg account')
            ->line('2. Go to **EduShare** section')
            ->line('3. Click on **"Shared with Me"** tab')
            ->line('4. Find the file: **' . $itemTitle . '**')
            ->line('---')
            ->line('This file has been shared to help you with your studies. Make the most of these resources!')
            ->salutation('Best regards,  
The UniOrg Team')
            ->theme('default');

        $message->viewData = [
            'primaryColor' => $theme['primary_color'],
            'complementaryColor' => $theme['complementary_color'],
            'organizationName' => 'UniOrg',
            'organizationLogo' => 'https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/public_image/LSPU_LOGO.png',
        ];

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // sharedBy is now a string (name)
        $sharerName = $this->sharedBy ?: 'Someone';
        
        if ($this->type === 'folder') {
            $folderName = $this->folder ? $this->folder->name : 'a folder';
            $folderId = $this->folder ? $this->folder->id : null;
            
            return [
                'type' => 'edushare',
                'edushare_type' => 'folder',
                'organization_id' => $this->folder?->organization_id,
                'folder_id' => $folderId,
                'folder_name' => $folderName,
                'shared_by' => $sharerName,
                'title' => $sharerName . ' shared a folder',
                'message' => 'Shared "' . $folderName . '" with you',
                'icon' => 'ph-folder',
                'color' => 'purple',
                'url' => $folderId ? route('edushare.index', ['folder' => $folderId, 'tab' => 'shared']) : route('edushare.index', ['tab' => 'shared']),
            ];
        }

        $itemTitle = $this->item ? $this->item->title : 'a file';
        $itemId = $this->item ? $this->item->id : null;
        
        return [
            'type' => 'edushare',
            'edushare_type' => 'item',
            'organization_id' => $this->item?->organization_id,
            'item_id' => $itemId,
            'item_title' => $itemTitle,
            'shared_by' => $sharerName,
            'title' => $sharerName . ' shared a file',
            'message' => 'Shared "' . $itemTitle . '" with you',
            'icon' => 'ph-file-text',
            'color' => 'purple',
            'url' => route('edushare.index', ['tab' => 'shared']),
        ];
    }
}
