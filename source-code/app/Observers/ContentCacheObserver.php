<?php

namespace App\Observers;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\LibraryResource;
use App\Services\ContentCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

class ContentCacheObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private ContentCache $contentCache) {}

    public function created(Model $model): void
    {
        $this->invalidate($model);
    }

    public function updated(Model $model): void
    {
        if ($this->isDownloadCountOnlyUpdate($model)) {
            return;
        }

        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        match (true) {
            $model instanceof Event => $this->contentCache->invalidateEvents(),
            $model instanceof Announcement => $this->contentCache->invalidateAnnouncements(),
            $model instanceof LibraryResource => $this->contentCache->invalidateLibraryResources(),
        };
    }

    private function isDownloadCountOnlyUpdate(Model $model): bool
    {
        if (! $model instanceof LibraryResource) {
            return false;
        }

        return array_diff(array_keys($model->getChanges()), ['download_count', 'updated_at']) === [];
    }
}
