<?php

namespace App\Listeners;

use App\Events\PaymentStatusChanged;
use App\Services\RealtimeNotificationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPaymentStatusNotification implements ShouldBeUnique, ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function handle(PaymentStatusChanged $event, RealtimeNotificationService $notifications): void
    {
        $notifications->paymentStatusChanged($event->order->loadMissing('user'), $event->previousStatus, $event->currentStatus);
    }

    public function uniqueId(PaymentStatusChanged $event): string
    {
        return $event->order->id.'|'.$event->currentStatus.'|'.$event->order->updated_at?->getTimestamp();
    }
}
