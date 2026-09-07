<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client\V1;

use App\Enums\PayWayStatus;
use App\Http\Requests\Api\Client\V1\AbaPayWayPurchaseRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\AbaPayWayService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class AbaPayWayController extends BaseApiController
{
    public function __construct(
        private readonly AbaPayWayService $payWay,
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * List payment methods available to the web or mobile client.
     *
     * Returns all available payment methods for the specified platform. Use `platform=web` for
     * a PayWay popup checkout that opens in a modal. Use `platform=mobile` for hosted checkout
     * and provide `return_deeplink` when starting payment for native app handling.
     *
     * Credentials are never returned by this endpoint. The `enabled` field indicates whether
     * a payment method is configured on the server.
     *
     * @queryParam platform string optional Client platform. Must be `web` or `mobile`. Defaults to `web`. Example: mobile
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "Payment options retrieved successfully.",
     *   "data": {
     *     "platform": "mobile",
     *     "checkout": {
     *       "view_type": "hosted_view",
     *       "requires_return_deeplink": true
     *     },
     *     "currency": ["USD", "KHR"],
     *     "methods": [
     *       {
     *         "key": "aba_payway",
     *         "enabled": true,
     *         "options": ["abapay_khqr", "cards"]
     *       },
     *       {
     *         "key": "cod",
     *         "enabled": true
     *       }
     *     ]
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

        return $this->success($this->payWay->paymentOptions($platform), 'Payment options retrieved successfully.');
    }

    /**
     * Create an ABA PayWay checkout for an authenticated customer's order.
     *
     * Initiates a payment checkout with ABA PayWay for the customer's order. The response includes
     * a `transaction_id` that should be stored and used for status checks. The `gateway` field
     * contains the PayWay response with checkout details (e.g., QR code URL for hosted view).
     *
     * For **web clients**: Omit `platform` or send `platform=web` to use popup checkout (the PayWay
     * iframe opens in a modal). Do not send `return_deeplink`.
     *
     * For **mobile clients**: Send `platform=mobile` and a base64-encoded `return_deeplink` payload.
     * The request defaults to `view_type=hosted_view` for mobile. The deeplink enables native app
     * return handling instead of browser fallback.
     *
     * @authenticated
     *
     * @permission client.orders.manage
     *
     * @bodyParam order_id integer required The authenticated customer's order ID. Example: 1042
     * @bodyParam payment_option string optional PayWay payment channel: `abapay_khqr` (QR code) or `cards` (credit/debit card). Example: abapay_khqr
     * @bodyParam platform string optional Client platform: `web` or `mobile`. Defaults to `web`. Example: mobile
     * @bodyParam view_type string optional Checkout presentation: `popup` (modal) or `hosted_view` (redirect). Defaults to `popup` for web, `hosted_view` for mobile. Example: hosted_view
     * @bodyParam return_url string required Absolute HTTPS or HTTP URL where PayWay POSTs the payment result. Must be accessible from PayWay's servers. Example: https://shop.example.com/payment/return
     * @bodyParam cancel_url string optional Absolute URL to redirect to if the customer cancels checkout. Example: https://shop.example.com/payment/cancel
     * @bodyParam continue_success_url string optional Absolute URL to redirect to after successful payment. Example: https://shop.example.com/orders/1042/paid
     * @bodyParam return_deeplink string optional Base64-encoded JSON payload for native mobile app return handling. Required for mobile native apps; ignored for web. Example: eyJpb3Nfc2NoZW1lIjoiYmVraWU6Ly9wYXltZW50LXJldHVybiIsImFuZHJvaWRfc2NoZW1lIjoiYmVraWU6Ly9wYXltZW50LXJldHVybiJ9
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "ABA PayWay checkout created.",
     *   "data": {
     *     "transaction_id": "BK1042A1B2C3D4E5",
     *     "view_type": "hosted_view",
     *     "gateway": {
     *       "status": {
     *         "code": "00",
     *         "message": "Success!"
     *       },
     *       "checkout_qr_url": "https://checkout-sandbox.payway.com.kh/qr/...",
     *       "html": "<form>...</form>"
     *     }
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
     *   "message": "Order currency is not supported by ABA PayWay.",
     *   "errors": {}
     * }
     * @response 422 {
     *   "status": "error",
     *   "message": "The order_id field is required.",
     *   "errors": {
     *     "order_id": ["The order_id field is required."]
     *   }
     * }
     * @response 422 {
     *   "status": "error",
     *   "message": "The return_url field is required.",
     *   "errors": {
     *     "return_url": ["The return_url field is required."]
     *   }
     * }
     * @response 422 {
     *   "status": "error",
     *   "message": "The selected payment_option is invalid.",
     *   "errors": {
     *     "payment_option": ["The selected payment_option is invalid."]
     *   }
     * }
     * @response 502 {
     *   "status": "error",
     *   "message": "Unable to create the ABA PayWay checkout.",
     *   "errors": {}
     * }
     */
    public function purchase(AbaPayWayPurchaseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = Order::query()
            ->whereKey($validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->first();

        if (! $order) {
            return $this->error('Order not found.', 404);
        }
        if ($order->payment_status === 'paid') {
            return $this->error('Order is already paid.', 422);
        }
        if (! in_array($order->currency, ['USD', 'KHR'], true)) {
            return $this->error('Order currency is not supported by ABA PayWay.', 422);
        }

        $payment = $this->paymentService->findPendingByOrderId($order->id);
        if ($payment && $payment->provider_payment_id) {
            Log::info('Idempotent purchase request', [
                'order_id' => $order->id,
                'existing_transaction_id' => $payment->provider_payment_id,
            ]);

            return $this->success([
                'transaction_id' => $payment->provider_payment_id,
                'view_type' => $payment->metadata['view_type'] ?? 'popup',
                'gateway' => $payment->gateway_response,
            ], 'ABA PayWay checkout created.');
        }

        try {
            return DB::transaction(function () use ($order, $request, $validated) {
                $customer = $order->customer_snapshot ?? [];
                $name = preg_split('/\s+/', trim((string) ($customer['name'] ?? 'Customer')), 2);
                $transactionId = Str::upper(Str::random(20));

                $result = $this->payWay->purchase([
                    'tran_id' => $transactionId,
                    'amount' => $order->grand_total,
                    'shipping' => $order->shipping_total,
                    'currency' => $order->currency,
                    'items' => $order->items->map(fn ($item) => [
                        'name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'price' => (float) $item->total,
                    ])->values()->all(),
                    'firstname' => $name[0] ?? 'Customer',
                    'lastname' => $name[1] ?? '',
                    'email' => $customer['email'] ?? $request->user()->email,
                    'phone' => $customer['phone'] ?? $request->user()->phone ?? '',
                    'payment_option' => $validated['payment_option'] ?? '',
                    'view_type' => $validated['view_type'],
                    'return_url' => $validated['return_url'],
                    'cancel_url' => $validated['cancel_url'] ?? '',
                    'continue_success_url' => $validated['continue_success_url'] ?? '',
                    'return_deeplink' => $validated['return_deeplink'] ?? '',
                    'return_params' => json_encode(['order_id' => $order->id], JSON_THROW_ON_ERROR),
                ]);

                $order->update([
                    'payment_method' => 'aba_payway',
                    'transaction_id' => $result['transaction_id'],
                    'payment_status' => 'pending',
                ]);

                $this->paymentService->createFromAbaPayWay($order, $result, $request);

                return $this->success($result, 'ABA PayWay checkout created.');
            });
        } catch (RuntimeException $exception) {
            Log::error('ABA PayWay purchase failed', [
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'message' => $exception->getMessage(),
            ]);

            return $this->error('Unable to create the ABA PayWay checkout.', 502);
        }
    }

    /**
     * Check the payment status of an ABA PayWay transaction belonging to the customer.
     *
     * Queries ABA PayWay's servers for the current status of a checkout. The transaction must belong
     * to the authenticated customer's order (verified by looking up the transaction_id in the customer's
     * orders). Returns PayWay's status response including payment status code and currency.
     *
     * **Note**: The response includes nested `data.data` structure because the outer `data` is our API wrapper
     * and the inner `data` comes from PayWay's response. This mirrors PayWay's API contract exactly.
     *
     * @authenticated
     *
     * @permission client.orders.manage
     *
     * @urlParam transactionId string required The transaction ID returned by the purchase endpoint. Example: BK1042A1B2C3D4E5
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "Transaction status retrieved.",
     *   "data": {
     *     "data": {
     *       "payment_status_code": 2,
     *       "payment_status": "PENDING",
     *       "payment_currency": "USD",
     *       "status": {
     *         "code": "00",
     *         "message": "Success!"
     *       }
     *     }
     *   }
     * }
     * @response 404 {
     *   "status": "error",
     *   "message": "Transaction not found.",
     *   "errors": {}
     * }
     * @response 502 {
     *   "status": "error",
     *   "message": "Unable to check the ABA PayWay transaction.",
     *   "errors": {}
     * }
     */
    public function check(Request $request, string $transactionId): JsonResponse
    {
        $payment = Payment::where('provider', 'aba_payway')
            ->where('provider_payment_id', $transactionId)
            ->first();

        if (! $payment || $payment->user_id !== $request->user()->id) {
            Log::warning('Unauthorized transaction status check attempt', [
                'transaction_id' => $transactionId,
                'user_id' => $request->user()->id,
                'payment_user_id' => $payment?->user_id,
            ]);

            return $this->error('Transaction not found.', 404);
        }

        try {
            return $this->success($this->payWay->check($transactionId), 'Transaction status retrieved.');
        } catch (RuntimeException $exception) {
            Log::error('ABA PayWay status check failed', [
                'transaction_id' => $transactionId,
                'user_id' => $request->user()->id,
                'message' => $exception->getMessage(),
            ]);

            return $this->error('Unable to check the ABA PayWay transaction.', 502);
        }
    }

    /**
     * Receive and verify the PayWay payment callback (webhook).
     *
     * **Public endpoint** — PayWay calls this directly after payment completion or cancellation.
     * The signature in the `X-PayWay-Hmac-Sha512` header is verified using HMAC-SHA512 before
     * the order status is updated. The request body contains the transaction result and metadata.
     *
     * When `status=0` (approved), the order payment status is set to `paid` with a timestamp.
     * When `status=200` or `201` (canceled/declined), the order payment status is set to `failed`.
     * Other statuses are logged but do not update the order.
     *
     * @header X-PayWay-Hmac-Sha512 string required HMAC-SHA512 signature for request verification. Generated by PayWay using the api_key. Example: r4tBvZ2x...
     *
     * @bodyParam tran_id string required ABA PayWay transaction ID matching the checkout. Example: BK1042A1B2C3D4E5
     * @bodyParam status string required PayWay result status code. `0` = approved/paid; `200` = canceled; `201` = declined. Example: 0
     * @bodyParam return_params string required JSON string containing context passed during checkout (e.g., order_id). Example: {"order_id":1042}
     * @bodyParam merchant_id string optional Merchant ID from PayWay. Example: BEKIE
     * @bodyParam req_time string optional Request timestamp from PayWay. Example: 20260906143022
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "PayWay callback accepted.",
     *   "data": {
     *     "order_id": 1042
     *   }
     * }
     * @response 401 {
     *   "status": "error",
     *   "message": "Invalid PayWay callback signature.",
     *   "errors": {}
     * }
     * @response 404 {
     *   "status": "error",
     *   "message": "Order not found.",
     *   "errors": {}
     * }
     */
    public function callback(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $signature = $request->header('X-PayWay-Hmac-Sha512');
        $transactionId = (string) ($payload['tran_id'] ?? 'unknown');

        if (! $this->payWay->verifyCallback($payload, $signature)) {
            Log::warning('PayWay callback signature verification failed', [
                'transaction_id' => $transactionId,
                'ip_address' => $request->ip(),
            ]);

            return $this->error('Invalid PayWay callback signature.', 401);
        }

        $returnParams = $this->parseReturnParams($payload['return_params'] ?? '{}');
        if (! isset($returnParams['order_id'])) {
            Log::warning('PayWay callback missing order_id in return_params', [
                'transaction_id' => $transactionId,
            ]);

            return $this->error('Order not found.', 404);
        }

        $order = Order::find($returnParams['order_id']);
        if (! $order) {
            Log::warning('PayWay callback for non-existent order', [
                'order_id' => $returnParams['order_id'],
                'transaction_id' => $transactionId,
            ]);

            return $this->error('Order not found.', 404);
        }

        try {
            return DB::transaction(function () use ($order, $payload, $transactionId) {
                $payment = Payment::where('provider_payment_id', $transactionId)
                    ->where('order_id', $order->id)
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    Log::warning('PayWay callback for transaction without payment record', [
                        'order_id' => $order->id,
                        'transaction_id' => $transactionId,
                    ]);

                    return $this->error('Order not found.', 404);
                }

                if ($payment->status !== 'pending') {
                    Log::info('PayWay callback for already-processed transaction (duplicate)', [
                        'order_id' => $order->id,
                        'transaction_id' => $transactionId,
                        'current_status' => $payment->status,
                    ]);

                    return $this->success(['order_id' => $order->id], 'PayWay callback accepted.');
                }

                $status = PayWayStatus::tryFrom((string) ($payload['status'] ?? ''));
                if (! $status) {
                    Log::warning('PayWay callback with unknown status', [
                        'order_id' => $order->id,
                        'transaction_id' => $transactionId,
                        'status' => $payload['status'] ?? 'null',
                    ]);

                    return $this->success(['order_id' => $order->id], 'PayWay callback accepted.');
                }

                if ($status->isPaid()) {
                    $this->paymentService->markAsPaid($payment, $payload);
                    $order->update(['payment_status' => 'paid', 'paid_at' => now()]);
                    Log::info('PayWay payment approved', [
                        'order_id' => $order->id,
                        'transaction_id' => $transactionId,
                    ]);
                } elseif ($status->isFailed()) {
                    $this->paymentService->markAsFailed($payment, $payload);
                    $order->update(['payment_status' => 'failed']);
                    Log::info('PayWay payment failed', [
                        'order_id' => $order->id,
                        'transaction_id' => $transactionId,
                        'payway_status' => $status->value,
                    ]);
                }

                return $this->success(['order_id' => $order->id], 'PayWay callback accepted.');
            });
        } catch (\Throwable $exception) {
            Log::error('PayWay callback processing failed', [
                'order_id' => $order->id,
                'transaction_id' => $transactionId,
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    private function parseReturnParams(string $json): array
    {
        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            Log::warning('Failed to parse PayWay return_params', ['json' => $json]);

            return [];
        }
    }
}
