<?php

namespace App\Events;

use App\Models\LibraryResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LibraryResourceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $resource;

    public function __construct(LibraryResource $resource)
    {
        $this->resource = $resource;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('library'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'library-resource-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'description_short' => \Str::limit($this->resource->description, 100),
            'resource_type' => $this->resource->resource_type,
            'url' => $this->resource->url,
            'file_path' => $this->resource->file_path,
            'download_count' => $this->resource->download_count ?? 0,
            'is_published' => $this->resource->is_published,
        ];
    }
}
