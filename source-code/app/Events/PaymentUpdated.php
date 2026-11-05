<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('payments'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payment-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->payment->id,
            'title' => $this->payment->title,
            'category' => $this->payment->category,
            'organization_id' => $this->payment->organization_id,
            'is_required_membership_fee' => $this->payment->is_required_membership_fee,
            'membership_cycle_start' => $this->payment->membership_cycle_start?->toDateString(),
        ];
    }
}
