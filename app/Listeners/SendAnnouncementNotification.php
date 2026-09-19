<?php

namespace App\Listeners;

use App\Events\AnnouncementPublished;
use App\Services\RealtimeNotificationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendAnnouncementNotification implements ShouldBeUnique, ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function handle(AnnouncementPublished $event, RealtimeNotificationService $notifications): void
    {
        $notifications->announcementPublished($event->announcement);
    }

    public function uniqueId(AnnouncementPublished $event): string
    {
        return (string) $event->announcement->id;
    }
}
