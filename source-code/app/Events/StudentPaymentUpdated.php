<?php

namespace App\Events;

use App\Models\StudentPayment;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentPaymentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public readonly int $studentPaymentId;
    public readonly int $userId;
    public readonly int $organizationId;
    public readonly string $action;

    public function __construct(StudentPayment $payment, string $action)
    {
        $this->studentPaymentId = (int) $payment->getKey();
        $this->userId = (int) $payment->user_id;
        $this->organizationId = (int) $payment->organization_id;
        $this->action = $action;
        $this->broadcastVia('pusher');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('student-payments.'.$this->userId),
            new PrivateChannel('organization-payments.'.$this->organizationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'student-payment-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'student_payment_id' => $this->studentPaymentId,
            'user_id' => $this->userId,
            'organization_id' => $this->organizationId,
            'action' => $this->action,
        ];
    }
}
