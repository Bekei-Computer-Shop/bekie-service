<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\RealtimeNotificationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderStatusNotification implements ShouldBeUnique, ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function handle(OrderStatusChanged $event, RealtimeNotificationService $notifications): void
    {
        $notifications->orderStatusChanged($event->order->loadMissing('user'), $event->previousStatus, $event->currentStatus);
    }

    public function uniqueId(OrderStatusChanged $event): string
    {
        return $event->order->id.'|'.$event->currentStatus.'|'.$event->order->updated_at?->getTimestamp();
    }
}
