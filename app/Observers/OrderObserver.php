<?php

namespace App\Observers;

use App\Events\OrderStatusChanged;
use App\Events\PaymentStatusChanged;
use App\Models\Order;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if ($order->wasChanged('status')) {
            OrderStatusChanged::dispatch(
                $order,
                $order->getOriginal('status'),
                (string) $order->status,
            );
        }

        if ($order->wasChanged('payment_status')) {
            PaymentStatusChanged::dispatch(
                $order,
                $order->getOriginal('payment_status'),
                (string) $order->payment_status,
            );
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }
}
