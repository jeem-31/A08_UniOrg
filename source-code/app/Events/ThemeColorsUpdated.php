<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThemeColorsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $organizationId;
    public $primaryColor;
    public $complementaryColor;
    public $logoUrl;

    /**
     * Create a new event instance.
     */
    public function __construct($organizationId, $primaryColor, $complementaryColor, $logoUrl = null)
    {
        $this->organizationId = $organizationId;
        $this->primaryColor = $primaryColor;
        $this->complementaryColor = $complementaryColor;
        $this->logoUrl = $logoUrl;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('organization.' . $this->organizationId);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'theme.colors.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'primary_color' => $this->primaryColor,
            'complementary_color' => $this->complementaryColor,
            'logo_url' => $this->logoUrl,
        ];
    }
}
