<?php

namespace App\Notifications;

use App\Traits\HasOrganizationTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ComplaintResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable, HasOrganizationTheme;

    public $complaint;
    public $adminResponse;

    /**
     * Create a new notification instance.
     */
    public function __construct($complaint, $adminResponse)
    {
        $this->complaint = $complaint;
        $this->adminResponse = $adminResponse;
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
    public function toMail(object $notifiable): MailMessage
    {
        $theme = $this->getOrganizationTheme($notifiable);
        $typeLabel = ucfirst($this->complaint->type);
        $orgName = $theme['organization_name'];
        $orgAcronym = $theme['organization_acronym'];
        
        $message = (new MailMessage)
            ->from($theme['from_email'] ?? config('mail.from.address'), $orgAcronym)
            ->subject('Your ' . $typeLabel . ' Has Been Resolved - ' . $orgName)
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('Great news! Your **' . strtolower($typeLabel) . '** has been resolved by our admin team.')
            ->line('---')
            ->line('**' . $typeLabel . ' Subject:**')
            ->line($this->complaint->subject)
            ->line('')
            ->line('**Your Message:**')
            ->line($this->complaint->message)
            ->line('---')
            ->line('**Admin Response:**')
            ->line($this->adminResponse)
            ->line('---')
            ->line('You can view this in your ' . $orgName . ' account under "My Submissions" section.')
            ->line('Thank you for bringing this to our attention. We appreciate your feedback!')
            ->salutation('Best regards,  
The ' . $orgName . ' Team  
College of Computer Studies - LSPU')
            ->theme('default');

        $message->viewData = [
            'organizationName' => $orgName,
            'organizationAcronym' => $orgAcronym,
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
        return [
            'complaint_id' => $this->complaint->id,
            'organization_id' => $this->complaint->organization_id,
            'subject' => $this->complaint->subject,
            'type' => $this->complaint->type,
            'status' => 'resolved',
            'admin_response' => $this->adminResponse,
            'title' => 'Complaint Resolved',
            'message' => 'Your ' . $this->complaint->type . ' "' . $this->complaint->subject . '" has been resolved.',
            'icon' => 'ph-check-circle',
            'color' => 'green',
            'url' => url('/complaints?view=' . $this->complaint->id),
        ];
    }
}
