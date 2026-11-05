<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCreated implements ShouldBroadcastNow
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
        return 'payment-created';
    }

    public function broadcastWith(): array
    {
        // The annual Membership Fee is system-created and deliberately has no
        // human author. Keep ordinary payment payloads backward compatible.
        if (!$this->payment->relationLoaded('user')) {
            $this->payment->load('user');
        }
        
        return [
            'payment' => [
                'id' => $this->payment->id,
                'title' => $this->payment->title,
                'category' => $this->payment->category,
                'amount' => $this->payment->amount,
                'organization_id' => $this->payment->organization_id,
                'is_required_membership_fee' => $this->payment->is_required_membership_fee,
                'membership_cycle_start' => $this->payment->membership_cycle_start?->toDateString(),
                'due_date' => $this->payment->due_date ? \Carbon\Carbon::parse($this->payment->due_date)->toIso8601String() : null,
                'gcash_number' => $this->payment->gcash_number,
                'qr_code' => $this->payment->qr_code,
                'qr_code_url' => $this->payment->qr_code ? storage_url($this->payment->qr_code) : null,
                'created_at' => $this->payment->created_at ? \Carbon\Carbon::parse($this->payment->created_at)->toIso8601String() : null,
                'user' => $this->payment->user ? [
                    'id' => $this->payment->user->id,
                    'name' => $this->payment->user->name,
                ] : null,
            ]
        ];
    }
}
