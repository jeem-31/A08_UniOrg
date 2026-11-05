<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MembershipFeeEligibilityUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly bool $eligible,
        public readonly ?int $membershipPaymentId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('membership-fee.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'membership-fee-eligibility-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'eligible' => $this->eligible,
            'membership_payment_id' => $this->membershipPaymentId,
        ];
    }
}
