<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

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

    public function reconcileAbaPayWay(Payment $payment, array $details): void
    {
        $providerTransactionId = $payment->provider_payment_id;
        if (($details['tran_id'] ?? null) !== null && $details['tran_id'] !== $providerTransactionId) {
            throw new AbaPayWayException('ABA PayWay transaction ID did not match.', [
                'aba' => ['reason_code' => 'check_transaction_id_mismatch'],
            ]);
        }

        $statusCode = (string) ($details['payment_status_code'] ?? '');
        $status = strtolower((string) ($details['payment_status'] ?? ''));
        $isPaid = in_array($statusCode, ['0', '00'], true)
            && in_array($status, ['approved', 'paid', 'success', 'successful'], true);
        $isFailed = in_array($statusCode, ['3', '7', '200', '201'], true)
            || in_array($status, ['cancelled', 'canceled', 'declined', 'failed', 'expired'], true);

        if (! $isPaid && ! $isFailed) {
            return;
        }

        if ($isPaid && ! isset($details['payment_amount'], $details['payment_currency'])) {
            throw new AbaPayWayException('ABA PayWay approved status is missing settled payment details.', [
                'aba' => ['reason_code' => 'check_payment_details_incomplete'],
            ]);
        }

        if ($isPaid
            && (number_format((float) $details['payment_amount'], 2, '.', '') !== number_format((float) $payment->amount, 2, '.', '')
                || strtoupper((string) $details['payment_currency']) !== strtoupper((string) $payment->currency))) {
            throw new AbaPayWayException('ABA PayWay transaction amount or currency did not match.', [
                'aba' => ['reason_code' => 'check_payment_details_mismatch'],
            ]);
        }

        DB::transaction(function () use ($payment, $details, $isPaid): void {
            $lockedPayment = Payment::query()->lockForUpdate()->find($payment->id);
            if (! $lockedPayment || $lockedPayment->status !== 'pending') {
                return;
            }

            $order = Order::query()->lockForUpdate()->find($lockedPayment->order_id);
            if (! $order) {
                throw new RuntimeException('ABA PayWay order was not found.');
            }

            $verifiedResponse = [
                'payment_status_code' => $details['payment_status_code'] ?? null,
                'payment_status' => $details['payment_status'] ?? null,
                'payment_amount' => $details['payment_amount'],
                'payment_currency' => $details['payment_currency'],
                'reconciled_at' => now()->toIso8601String(),
            ];

            if ($isPaid) {
                $lockedPayment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'gateway_response' => $verifiedResponse,
                ]);

                if ($order->payment_status !== 'paid') {
                    $order->update([
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                    ]);
                    app(ProductSerialService::class)->finalizeOrder((int) $order->id);
                }

                return;
            }

            $lockedPayment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'gateway_response' => $verifiedResponse,
            ]);

            if ($order->payment_status !== 'paid') {
                $order->update(['payment_status' => 'failed']);
                app(ProductSerialService::class)->releaseOrder((int) $order->id, null, 'Payment failed.');
            }
        });
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

    public function createPendingAbaPayWay(Order $order, string $transactionId, Request $request): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'payment_reference' => 'PAY-'.now()->format('YmdH').'-'.Str::upper(Str::random(6)),
            'provider' => 'aba_payway',
            'provider_payment_id' => $transactionId,
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'status' => 'pending',
            'payment_method' => 'aba_payway',
            'metadata' => [
                'initiated_at' => now()->toIso8601String(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
