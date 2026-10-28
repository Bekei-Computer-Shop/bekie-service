<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\AbaPayWayPurchaseRequest;
use App\Jobs\ProcessAbaPayWayCallback;
use App\Models\Order;
use App\Models\Payment;
use App\Services\AbaPayWayException;
use App\Services\AbaPayWayService;
use App\Services\PaymentService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
     *         "options": ["abapay_khqr_deeplink", "cards"]
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
     * KHQR purchases use popup view so PayWay returns the QR JSON response.
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
     * @bodyParam payment_option string optional PayWay payment channel: `abapay_khqr_deeplink` (KHQR payload and ABA Mobile deeplink) or `cards` (credit/debit card). Example: abapay_khqr_deeplink
     * @bodyParam platform string optional Client platform: `web` or `mobile`. Defaults to `web`. Example: mobile
     * @bodyParam view_type string optional Checkout presentation: `popup` (modal) or `hosted_view` (redirect). Defaults to `popup` for web, `hosted_view` for mobile. Example: hosted_view
     * @bodyParam return_url string optional Ignored. PayWay's callback URL is generated from the server APP_URL.
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
     *       "payment_payload": {
     *         "type": "qr_string",
     *         "value": "SANDBOX_QR_PAYLOAD"
     *       },
     *       "qr_string": "SANDBOX_QR_PAYLOAD",
     *       "abapay_deeplink": "abamobilebank://ababank.com?type=payway&qrcode=...",
     *       "checkout_url": "https://checkout-sandbox.payway.com.kh/...",
     *       "gateway": {
     *         "status": { "code": "00" },
     *         "qr_string": "SANDBOX_QR_PAYLOAD"
     *       }
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
     *   "message": "The selected payment_option is invalid.",
     *   "errors": {
     *     "payment_option": ["The selected payment_option is invalid."]
     *   }
     * }
     * @response 502 {
     *   "status": "error",
     *   "message": "Return URL is not whitelisted.",
     *   "errors": {
     *     "aba": {
     *       "status_code": "81",
     *       "http_status": 200,
     *       "message": "Return URL is not whitelisted."
     *     }
     *   }
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

        $lock = Cache::lock('aba-payway:purchase:order:'.$order->id, 75);
        if (! $lock->get()) {
            return $this->error('ABA PayWay setup is already in progress for this order.', 409);
        }

        try {
            [$payment, $isNewPayment, $cachedResponse] = DB::transaction(function () use ($order, $request): array {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                if ($lockedOrder->payment_status === 'paid') {
                    throw new RuntimeException('Order is already paid.');
                }

                $payment = Payment::query()
                    ->where('provider', 'aba_payway')
                    ->where('order_id', $lockedOrder->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->latest()
                    ->first();

                if ($payment && ((float) $payment->amount !== (float) $lockedOrder->grand_total
                    || $payment->currency !== $lockedOrder->currency)) {
                    throw new RuntimeException('A pending ABA PayWay payment does not match the order total.');
                }

                if (! $payment) {
                    $payment = $this->paymentService->createPendingAbaPayWay(
                        $lockedOrder,
                        Str::upper(Str::random(20)),
                        $request,
                    );
                    $isNewPayment = true;
                } else {
                    $isNewPayment = false;
                }

                $lockedOrder->update([
                    'payment_method' => 'aba_payway',
                    'transaction_id' => $payment->provider_payment_id,
                    'payment_status' => 'pending',
                ]);

                $cachedCheckout = data_get($payment->metadata, 'checkout');
                $cachedResponse = is_array($cachedCheckout)
                    && data_get($cachedCheckout, 'payment_payload.type') === 'qr_string'
                    ? $cachedCheckout
                    : null;

                return [$payment, $isNewPayment, $cachedResponse];
            });

            if (is_array($cachedResponse)) {
                return $this->success($cachedResponse, 'ABA PayWay checkout already created.');
            }

            if (! $isNewPayment) {
                $providerStatus = $this->payWay->check((string) $payment->provider_payment_id, allowNotFound: true);
                if ((string) data_get($providerStatus, 'status.code', '') !== '6') {
                    return $this->error('An ABA PayWay transaction already exists for this order; it will not be duplicated.', 409);
                }
            }

            $customer = $order->customer_snapshot ?? [];
            $name = preg_split('/\s+/', trim((string) ($customer['name'] ?? 'Customer')), 2);
            $result = $this->payWay->purchase([
                'tran_id' => $payment->provider_payment_id,
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
                'view_type' => in_array(($validated['payment_option'] ?? ''), ['abapay_khqr', 'abapay_khqr_deeplink'], true)
                    ? 'popup'
                    : $validated['view_type'],
                'cancel_url' => $validated['cancel_url'] ?? '',
                'continue_success_url' => $validated['continue_success_url'] ?? '',
                'return_deeplink' => $validated['return_deeplink'] ?? '',
                'return_params' => json_encode(['order_id' => $order->id], JSON_THROW_ON_ERROR),
            ]);

            $payment->refresh();
            $payment->update([
                'metadata' => array_merge($payment->metadata ?? [], [
                    'checkout' => $result,
                    'purchase_response_received_at' => now()->toIso8601String(),
                ]),
            ]);

            return $this->success($result, 'ABA PayWay checkout created.');
        } catch (ConnectionException $exception) {
            Log::error('ABA PayWay purchase connection failed', [
                'tran_id' => $order->transaction_id,
                'http_status' => null,
                'status_code' => null,
                'error_code' => class_basename($exception),
            ]);

            return $this->error('ABA PayWay is temporarily unavailable. Check the payment status before retrying.', 502);
        } catch (RuntimeException $exception) {
            if ($exception instanceof AbaPayWayException) {
                $errors = ['aba' => [
                    'status_code' => $exception->errors['aba']['status_code'] ?? null,
                    'http_status' => $exception->errors['aba']['http_status'] ?? null,
                    'reason_code' => $exception->errors['aba']['reason_code'] ?? null,
                ]];
                $message = 'Unable to set up ABA PayWay checkout.';
            } else {
                $errors = ['payment' => ['message' => 'The checkout could not be created.']];
                $message = 'Unable to create the ABA PayWay checkout.';
            }

            Log::error('ABA PayWay purchase failed', [
                'tran_id' => $order->transaction_id,
                'status' => 'failed',
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'timestamp' => now()->toIso8601String(),
                'aba_status_code' => $errors['aba']['status_code'] ?? null,
            ]);

            return $this->error($message, 502, $errors);
        } finally {
            $lock->release();
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
            $providerResponse = $this->payWay->check($transactionId);
            $this->paymentService->reconcileAbaPayWay($payment, $this->payWay->transactionDetails($providerResponse));

            return $this->success($providerResponse, 'Transaction status retrieved.');
        } catch (ConnectionException $exception) {
            $reasonCode = 'check_connection_failure';
            Log::error('ABA PayWay status check failed', [
                'tran_id' => $transactionId,
                'status' => $payment->status,
                'reason_code' => $reasonCode,
                'error_code' => class_basename($exception),
            ]);

            return $this->error('Unable to check the ABA PayWay transaction.', 502, ['reason_code' => $reasonCode]);
        } catch (RuntimeException $exception) {
            $reasonCode = $exception instanceof AbaPayWayException
                ? (string) data_get($exception->errors, 'aba.reason_code', 'check_failed')
                : 'check_reconciliation_failed';

            Log::error('ABA PayWay status check failed', [
                'tran_id' => $transactionId,
                'status' => $payment->status,
                'timestamp' => now()->toIso8601String(),
                'reason_code' => $reasonCode,
                'status_code' => $exception instanceof AbaPayWayException
                    ? data_get($exception->errors, 'aba.status_code')
                    : null,
                'error_code' => class_basename($exception),
            ]);

            return $this->error('Unable to check the ABA PayWay transaction.', 502, ['reason_code' => $reasonCode]);
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
        $transactionId = (string) ($payload['tran_id'] ?? '');

        if (! $this->payWay->verifyCallback($payload, $signature)
            || ! hash_equals((string) config('aba_payway.merchant_id'), (string) ($payload['merchant_id'] ?? ''))) {
            return $this->error('Invalid PayWay callback signature.', 401);
        }

        $payment = Payment::query()
            ->where('provider', 'aba_payway')
            ->where('provider_payment_id', $transactionId)
            ->first();
        if (! $payment) {
            return $this->error('Order not found.', 404);
        }

        $returnParams = $this->parseReturnParams((string) ($payload['return_params'] ?? '{}'));
        if (isset($returnParams['order_id']) && (int) $returnParams['order_id'] !== (int) $payment->order_id) {
            return $this->error('PayWay callback does not match the payment.', 422);
        }

        if ($payment->status === 'pending') {
            ProcessAbaPayWayCallback::dispatch($payment->id);
        }

        return $this->success(['accepted' => true], 'PayWay callback accepted.');
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
