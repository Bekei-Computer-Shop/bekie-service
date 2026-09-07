<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentService
{
    public function createFromAbaPayWay(
        Order $order,
        array $payWayResponse,
        Request $request
    ): Payment {
        return Payment::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'payment_reference' => 'PAY-'.now()->format('YmdH').'-'.Str::upper(Str::random(6)),
            'provider' => 'aba_payway',
            'provider_payment_id' => $payWayResponse['transaction_id'],
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'status' => 'pending',
            'payment_method' => 'aba_payway',
            'gateway_response' => $payWayResponse,
            'metadata' => [
                'view_type' => $payWayResponse['view_type'] ?? 'popup',
                'initiated_at' => now()->toIso8601String(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function markAsPaid(Payment $payment, array $callbackPayload): void
    {
        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'gateway_response' => array_merge(
                $payment->gateway_response ?? [],
                $callbackPayload
            ),
        ]);
    }

    public function markAsFailed(Payment $payment, array $callbackPayload): void
    {
        $payment->update([
            'status' => 'failed',
            'failed_at' => now(),
            'gateway_response' => array_merge(
                $payment->gateway_response ?? [],
                $callbackPayload
            ),
        ]);
    }

    public function findByProviderTransactionId(string $providerId): ?Payment
    {
        return Payment::where('provider', 'aba_payway')
            ->where('provider_payment_id', $providerId)
            ->first();
    }

    public function findPendingByOrderId(int $orderId): ?Payment
    {
        return Payment::where('order_id', $orderId)
            ->where('status', 'pending')
            ->latest()
            ->first();
    }
}
