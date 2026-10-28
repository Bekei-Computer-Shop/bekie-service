<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Payment;
use App\Services\AbaPayWayService;
use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAbaPayWayCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $paymentId) {}

    public function backoff(): array
    {
        return [5, 30, 120, 300];
    }

    public function handle(AbaPayWayService $payWay, PaymentService $payments): void
    {
        $payment = Payment::query()->find($this->paymentId);
        if (! $payment || $payment->status !== 'pending') {
            return;
        }

        $response = $payWay->check((string) $payment->provider_payment_id);
        $details = $payWay->transactionDetails($response);
        $payments->reconcileAbaPayWay($payment, $details);
    }

    public function failed(Throwable $exception): void
    {
        $payment = Payment::query()->find($this->paymentId);

        Log::error('ABA PayWay callback reconciliation exhausted retries', [
            'tran_id' => $payment?->provider_payment_id,
            'status' => $payment?->status,
            'amount' => $payment?->amount,
            'currency' => $payment?->currency,
            'timestamp' => now()->toIso8601String(),
            'error_code' => class_basename($exception),
        ]);
    }
}
