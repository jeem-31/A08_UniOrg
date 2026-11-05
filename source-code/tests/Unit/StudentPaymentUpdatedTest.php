<?php

namespace Tests\Unit;

use App\Events\StudentPaymentUpdated;
use App\Models\StudentPayment;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Tests\TestCase;

class StudentPaymentUpdatedTest extends TestCase
{
    public function test_it_broadcasts_the_minimum_payment_refresh_payload_over_private_pusher_channels(): void
    {
        $payment = new StudentPayment([
            'user_id' => 41,
            'organization_id' => 8,
        ]);
        $payment->setAttribute('id', 125);

        $event = new StudentPaymentUpdated($payment, 'receipt-uploaded');

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
        $this->assertSame(['pusher'], $event->broadcastConnections());
        $this->assertSame([
            'private-student-payments.41',
            'private-organization-payments.8',
        ], array_map(fn ($channel) => $channel->name, $event->broadcastOn()));
        $this->assertSame('student-payment-updated', $event->broadcastAs());
        $this->assertSame([
            'student_payment_id' => 125,
            'user_id' => 41,
            'organization_id' => 8,
            'action' => 'receipt-uploaded',
        ], $event->broadcastWith());
    }
}
