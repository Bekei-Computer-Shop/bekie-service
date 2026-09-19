<?php

namespace App\Observers;

use App\Events\AnnouncementPublished;
use App\Models\ContentItem;

class ContentItemObserver
{
    public function created(ContentItem $contentItem): void
    {
        $this->dispatchIfPublished($contentItem);
    }

    public function updated(ContentItem $contentItem): void
    {
        if ($contentItem->wasChanged('status')) {
            $this->dispatchIfPublished($contentItem);
        }
    }

    private function dispatchIfPublished(ContentItem $contentItem): void
    {
        if ($contentItem->type === 'news' && $contentItem->status === 'published') {
            AnnouncementPublished::dispatch($contentItem);
        }
    }

    /**
     * Handle the ContentItem "deleted" event.
     */
    public function deleted(ContentItem $contentItem): void
    {
        //
    }

    /**
     * Handle the ContentItem "restored" event.
     */
    public function restored(ContentItem $contentItem): void
    {
        //
    }

    /**
     * Handle the ContentItem "force deleted" event.
     */
    public function forceDeleted(ContentItem $contentItem): void
    {
        //
    }
}
