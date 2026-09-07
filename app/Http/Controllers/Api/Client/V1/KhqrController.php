<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\KhqrGenerateRequest;
use App\Models\KhqrTransaction;
use App\Models\Order;
use App\Services\KhqrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class KhqrController extends BaseApiController
{
    public function __construct(private readonly KhqrService $khqr) {}

    /**
     * List available KHQR payment options.
     *
     * Returns payment options and configuration for KHQR QR code payments.
     * KHQR (Khmer Quick Response Code) is a QR-based payment standard used for
     * ABA bank transfers in Cambodia.
     *
     * @queryParam platform string optional Client platform: `web` or `mobile`. Defaults to `web`. Example: mobile
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "KHQR payment options retrieved.",
     *   "data": {
     *     "khqr": {
     *       "enabled": true,
     *       "name": "ABA KHQR",
     *       "description": "Scan QR code to pay via ABA bank",
     *       "supported_currencies": ["USD", "KHR"],
     *       "web_instructions": "Display QR code for customer to scan with mobile banking app",
     *       "mobile_instructions": "Customer scans QR code using their mobile banking app"
     *     }
     *   }
     * }
     * @response 422 {
     *   "status": "error",
     *   "message": "The selected platform is invalid.",
     *   "errors": {
     *     "platform": ["The selected platform is invalid."]
     *   }
     * }
     */
    public function options(Request $request): JsonResponse
    {
        $platform = $request->validate([
            'platform' => ['sometimes', 'in:web,mobile'],
        ])['platform'] ?? 'web';

        $options = $this->khqr->paymentOptions();

        $options['khqr']['web_instructions'] = 'Display QR code for customer to scan with mobile banking app';
        $options['khqr']['mobile_instructions'] = 'Customer scans QR code using their mobile banking app';

        return $this->success($options, 'KHQR payment options retrieved.');
    }

    /**
     * Generate KHQR QR code for payment.
     *
     * Generates a QR code for the customer to scan and pay via ABA bank using KHQR standard.
     * The QR code contains the order amount, currency, and merchant information.
     *
     * **Web Client Flow**:
     * - Request KHQR generation for the order
     * - Display QR code image to customer
     * - Customer scans with mobile banking app
     * - API polls `/payments/khqr/{transactionId}` to check status
     * - Or receives webhook callback when payment completes
     *
     * **Mobile Client Flow**:
     * - Request KHQR generation
     * - Display QR code or show payment details
     * - Customer enters amount in their banking app and completes payment
     * - App polls for payment confirmation
     *
     * @authenticated
     *
     * @permission client.orders.manage
     *
     * @bodyParam order_id integer required The customer's order ID to generate QR code for. Example: 1042
     * @bodyParam platform string optional Client platform: `web` or `mobile`. Defaults to `web`. Example: mobile
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "KHQR QR code generated successfully.",
     *   "data": {
     *     "transaction_id": "KHQR20260906143022AB1CD2EF",
     *     "qr_code_url": "data:image/png;base64,iVBORw0KGgoAAAANS...",
     *     "qr_code_data": "000201121129300012D156000700..."
     *     "merchant_id": "bekie",
     *     "merchant_name": "Bekie",
     *     "amount": 50.00,
     *     "currency": "USD",
     *     "reference": "ORD1042",
     *     "expires_at": "2026-09-07T14:30:22Z"
     *   }
     * }
     * @response 404 {
     *   "status": "error",
     *   "message": "Order not found.",
     *   "errors": {}
     * }
     * @response 422 {
     *   "status": "error",
     *   "message": "Order is already paid.",
     *   "errors": {}
     * }
     * @response 422 {
     *   "status": "error",
     *   "message": "Order currency is not supported by KHQR.",
     *   "errors": {}
     * }
     * @response 502 {
     *   "status": "error",
     *   "message": "Unable to generate KHQR QR code.",
     *   "errors": {}
     * }
     */
    public function generate(KhqrGenerateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $platform = $validated['platform'] ?? 'web';

        $order = Order::query()
            ->whereKey($validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $order) {
            return $this->error('Order not found.', 404);
        }

        if ($order->payment_status === 'paid') {
            return $this->error('Order is already paid.', 422);
        }

        if (! in_array($order->currency, ['USD', 'KHR'], true)) {
            return $this->error('Order currency is not supported by KHQR.', 422);
        }

        try {
            $result = $this->khqr->generateQrCode([
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'user_email' => $request->user()->email,
            ]);

            $order->update([
                'payment_method' => 'khqr',
                'transaction_id' => $result['transaction_id'],
                'payment_status' => 'pending',
            ]);

            return $this->success($result, 'KHQR QR code generated successfully.');
        } catch (RuntimeException $exception) {
            Log::error('KHQR QR code generation failed', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);

            return $this->error('Unable to generate KHQR QR code.', 502);
        }
    }

    /**
     * Check KHQR payment status.
     *
     * Checks the current payment status for a KHQR transaction. The transaction must belong
     * to the authenticated customer's order.
     *
     * Use this endpoint to poll for payment completion on web clients. Mobile clients can
     * also poll or wait for webhook notification.
     *
     * @authenticated
     *
     * @permission client.orders.manage
     *
     * @urlParam transactionId string required The KHQR transaction ID from the generate endpoint. Example: KHQR20260906143022AB1CD2EF
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "Payment status retrieved.",
     *   "data": {
     *     "payment_status": "pending",
     *     "expires_at": "2026-09-07T14:30:22Z"
     *   }
     * }
     * @response 200 {
     *   "status": "success",
     *   "message": "Payment status retrieved.",
     *   "data": {
     *     "payment_status": "paid",
     *     "paid_at": "2026-09-06T14:35:10Z",
     *     "payment_reference": "TXN20260906000001"
     *   }
     * }
     * @response 200 {
     *   "status": "success",
     *   "message": "Payment status retrieved.",
     *   "data": {
     *     "payment_status": "expired",
     *     "expired_at": "2026-09-07T14:30:22Z"
     *   }
     * }
     * @response 404 {
     *   "status": "error",
     *   "message": "Transaction not found.",
     *   "errors": {}
     * }
     */
    public function check(Request $request, string $transactionId): JsonResponse
    {
        $transaction = KhqrTransaction::where('transaction_id', $transactionId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $transaction) {
            return $this->error('Transaction not found.', 404);
        }

        $status = $this->khqr->checkStatus($transactionId);

        return $this->success($status, 'Payment status retrieved.');
    }

    /**
     * Confirm KHQR payment (webhook or manual).
     *
     * **Internal/Admin Use**: Confirms a KHQR payment when verified by the system.
     * This endpoint is called when payment is confirmed via webhook or manual verification.
     *
     * For customer-facing endpoints, use the `check` endpoint to query payment status.
     *
     * @authenticated
     *
     * @permission client.orders.manage
     *
     * @bodyParam transaction_id string required The KHQR transaction ID to confirm. Example: KHQR20260906143022AB1CD2EF
     * @bodyParam payment_reference string optional External payment reference from bank. Example: TXN20260906000001
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "KHQR payment confirmed.",
     *   "data": {}
     * }
     * @response 404 {
     *   "status": "error",
     *   "message": "Transaction not found.",
     *   "errors": {}
     * }
     */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'string'],
            'payment_reference' => ['nullable', 'string'],
        ]);

        $transaction = KhqrTransaction::where('transaction_id', $validated['transaction_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $transaction) {
            return $this->error('Transaction not found.', 404);
        }

        try {
            $this->khqr->confirmPayment(
                $validated['transaction_id'],
                $validated['payment_reference'] ?? ''
            );

            $transaction->order->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);

            return $this->success([], 'KHQR payment confirmed.');
        } catch (RuntimeException $exception) {
            Log::error('KHQR payment confirmation failed', [
                'transaction_id' => $validated['transaction_id'],
                'message' => $exception->getMessage(),
            ]);

            return $this->error('Unable to confirm KHQR payment.', 502);
        }
    }

    /**
     * Cancel KHQR payment request.
     *
     * Cancels a KHQR QR code payment request. Use this if the customer wants to choose
     * a different payment method before completing the payment.
     *
     * @authenticated
     *
     * @permission client.orders.manage
     *
     * @urlParam transactionId string required The KHQR transaction ID to cancel. Example: KHQR20260906143022AB1CD2EF
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "KHQR payment cancelled.",
     *   "data": {}
     * }
     * @response 404 {
     *   "status": "error",
     *   "message": "Transaction not found.",
     *   "errors": {}
     * }
     * @response 422 {
     *   "status": "error",
     *   "message": "Payment already completed.",
     *   "errors": {}
     * }
     */
    public function cancel(Request $request, string $transactionId): JsonResponse
    {
        $transaction = KhqrTransaction::where('transaction_id', $transactionId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $transaction) {
            return $this->error('Transaction not found.', 404);
        }

        if ($transaction->status === 'paid') {
            return $this->error('Payment already completed.', 422);
        }

        try {
            $this->khqr->cancel($transactionId);

            return $this->success([], 'KHQR payment cancelled.');
        } catch (RuntimeException $exception) {
            Log::error('KHQR payment cancellation failed', [
                'transaction_id' => $transactionId,
                'message' => $exception->getMessage(),
            ]);

            return $this->error('Unable to cancel KHQR payment.', 502);
        }
    }
}
