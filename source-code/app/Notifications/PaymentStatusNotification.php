<?php

namespace App\Notifications;

use App\Traits\HasOrganizationTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentStatusNotification extends Notification implements ShouldQueue
{
    use Queueable, HasOrganizationTheme;

    public $payment;
    public $status;
    public $message;

    /**
     * Create a new notification instance.
     */
    public function __construct($payment, $status, $message = null)
    {
        $this->payment = $payment;
        $this->status = $status;
        $this->message = $message;
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
        $statusLabel = ucfirst($this->status);
        
        if ($this->status === 'rejected') {
            $message = (new MailMessage)
                ->subject('Payment Rejected - Action Required - ' . $theme['organization_name'])
                ->greeting('Hello ' . $notifiable->first_name . ',')
                ->line('Unfortunately, your payment submission has been rejected.')
                ->line('---')
                ->line('**Payment Details:**')
                ->line('Transaction ID: ' . $this->payment->transaction_id)
                ->line('Amount: ₱' . number_format($this->payment->amount, 2))
                ->line('Category: ' . ucfirst($this->payment->category))
                ->line('')
                ->line('**Reason for Rejection:**')
                ->line($this->message ?? 'Please check your payment details and try again.')
                ->line('---')
                ->line('**How to Resubmit Your Payment:**')
                ->line('1. **Take a clear screenshot** of your GCash receipt')
                ->line('2. **Verify the reference number** matches your transaction')
                ->line('3. **Ensure the amount is correct** (₱' . number_format($this->payment->amount, 2) . ')')
                ->line('4. **Check that the screenshot is not blurry** or cut off')
                ->line('5. **Resubmit your payment** through your ' . $theme['organization_name'] . ' account')
                ->line('---')
                ->line('**Tips for Successful Submission:**')
                ->line('• Use a clear, high-quality screenshot')
                ->line('• Make sure all details are visible and readable')
                ->line('• Double-check the reference number before submitting')
                ->line('• Ensure the payment amount matches exactly')
                ->line('---')
                ->line('If you have questions or need assistance, please contact the ' . $theme['organization_acronym'] . ' office.')
                ->salutation('Best regards,  
The ' . $theme['organization_name'] . ' Team  
College of Computer Studies - LSPU')
                ->theme('default');

            $message->viewData = [
                'primaryColor' => $theme['primary_color'],
                'complementaryColor' => $theme['complementary_color'],
                'organizationName' => $theme['organization_name'],
                'organizationLogo' => 'https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/public_image/LSPU_LOGO.png',
            ];

            return $message;
        }
        
        $message = (new MailMessage)
            ->subject('Payment ' . $statusLabel . ' - ' . $theme['organization_name'])
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line($this->message ?? $this->getDefaultMessage())
            ->line('---')
            ->line('**Payment Details:**')
            ->line('Transaction ID: ' . $this->payment->transaction_id)
            ->line('Amount: ₱' . number_format($this->payment->amount, 2))
            ->line('Category: ' . ucfirst($this->payment->category))
            ->line('Status: ' . $statusLabel)
            ->line('---')
            ->line('You can view your payment history in your ' . $theme['organization_name'] . ' account.')
            ->line('Thank you for your payment!')
            ->salutation('Best regards,  
The ' . $theme['organization_name'] . ' Team  
College of Computer Studies - LSPU')
            ->theme('default');

        $message->viewData = [
            'primaryColor' => $theme['primary_color'],
            'complementaryColor' => $theme['complementary_color'],
            'organizationName' => $theme['organization_name'],
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
        $data = [
            'payment_id' => $this->payment->id,
            'organization_id' => $this->payment->organization_id,
            'transaction_id' => $this->payment->transaction_id,
            'amount' => $this->payment->amount,
            'category' => $this->payment->category,
            'status' => $this->status,
            'title' => $this->getTitle(),
            'message' => $this->message ?? $this->getDefaultMessage(),
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'url' => route('payments.index'),
        ];

        return $data;
    }

    private function getTitle()
    {
        return match($this->status) {
            'approved' => 'Payment Approved',
            'rejected' => 'Payment Rejected',
            'completed' => 'Payment Completed',
            default => 'Payment Update',
        };
    }

    private function getDefaultMessage()
    {
        return match($this->status) {
            'approved' => 'Your payment of ₱' . number_format($this->payment->amount, 2) . ' has been approved!',
            'rejected' => 'Your payment of ₱' . number_format($this->payment->amount, 2) . ' was rejected. Please check the details and try again.',
            'completed' => 'Your payment of ₱' . number_format($this->payment->amount, 2) . ' has been completed successfully!',
            default => 'Your payment status has been updated.',
        };
    }

    private function getIcon()
    {
        return match($this->status) {
            'approved' => 'ph-check-circle',
            'rejected' => 'ph-x-circle',
            'completed' => 'ph-check-circle',
            default => 'ph-info',
        };
    }

    private function getColor()
    {
        return match($this->status) {
            'approved' => 'green',
            'rejected' => 'red',
            'completed' => 'green',
            default => 'gray',
        };
    }
}
