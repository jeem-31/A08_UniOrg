<?php

namespace App\Events;

use App\Models\ProcessedDocument;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired every time a ProcessedDocument's processing_status changes.
 * Broadcast on a per-application channel so the OSAS show page only
 * receives updates for the application it is currently viewing.
 */
class DocumentAnalysisStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $applyOrgId,
        public readonly int    $analysisId,
        public readonly string $status,
        public readonly string $documentField,
        public readonly string $documentSlot,
        public readonly ?string $errorMessage,
    ) {}

    public function broadcastOn(): array
    {
        // Per-application channel: osas-application.{applyOrgId}
        return [
            new Channel("osas-application.{$this->applyOrgId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'document-analysis.status-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'analysis_id'    => $this->analysisId,
            'status'         => $this->status,
            'document_field' => $this->documentField,
            'document_slot'  => $this->documentSlot,
            'error_message'  => $this->errorMessage,
        ];
    }
}
