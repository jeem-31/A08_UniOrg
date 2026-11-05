<?php

namespace App\Events;

use App\Models\Complaint;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComplaintUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $complaint;

    public function __construct(Complaint $complaint)
    {
        $this->complaint = $complaint;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('complaints'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'complaint-updated';
    }

    public function broadcastWith(): array
    {
        // Load user relationship with full_name accessor
        $this->complaint->load('user:id,first_name,middle_name,last_name,suffix,email,student_id');
        
        return [
            'complaint' => [
                'id' => $this->complaint->id,
                'user_id' => $this->complaint->user_id,
                'subject' => $this->complaint->subject,
                'status' => $this->complaint->status,
                'type' => $this->complaint->type,
                'message' => $this->complaint->message,
                'is_anonymous' => $this->complaint->is_anonymous,
                'admin_response' => $this->complaint->admin_response,
                'responded_by' => $this->complaint->responded_by,
                'responded_at' => $this->complaint->responded_at,
                'created_at' => $this->complaint->created_at,
                'updated_at' => $this->complaint->updated_at,
                'user' => $this->complaint->user ? [
                    'id' => $this->complaint->user->id,
                    'first_name' => $this->complaint->user->first_name,
                    'middle_name' => $this->complaint->user->middle_name,
                    'last_name' => $this->complaint->user->last_name,
                    'suffix' => $this->complaint->user->suffix,
                    'email' => $this->complaint->user->email,
                    'student_id' => $this->complaint->user->student_id,
                    'full_name' => $this->complaint->user->full_name,
                ] : null,
            ],
        ];
    }
}
