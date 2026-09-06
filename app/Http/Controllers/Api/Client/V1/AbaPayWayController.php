<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client\V1;

use App\Models\Order;
use App\Http\Requests\Api\Client\V1\AbaPayWayPurchaseRequest;
use App\Services\AbaPayWayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class AbaPayWayController extends BaseApiController
{
    public function __construct(private readonly AbaPayWayService $payWay) {}

    public function options(Request $request): JsonResponse
    {
        $platform = $request->validate([
            'platform' => ['sometimes', 'in:web,mobile'],
        ])['platform'] ?? 'web';

        return $this->success($this->payWay->paymentOptions($platform), 'Payment options retrieved successfully.');
    }

    public function purchase(AbaPayWayPurchaseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = Order::query()
            ->whereKey($validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->first();

        if (! $order) return $this->error('Order not found.', 404);
        if ($order->payment_status === 'paid') return $this->error('Order is already paid.', 422);
        if (! in_array($order->currency, ['USD', 'KHR'], true)) return $this->error('Order currency is not supported by ABA PayWay.', 422);

        try {
            $customer = $order->customer_snapshot ?? [];
            $name = preg_split('/\s+/', trim((string) ($customer['name'] ?? 'Customer')), 2);
            $result = $this->payWay->purchase([
                'tran_id' => Str::upper('BK'.$order->id.Str::random(12)),
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

            return $this->success($result, 'ABA PayWay checkout created.');
        } catch (RuntimeException $exception) {
            Log::error('ABA PayWay purchase failed', ['order_id' => $order->id, 'message' => $exception->getMessage()]);
            return $this->error('Unable to create the ABA PayWay checkout.', 502);
        }
    }

    public function check(Request $request, string $transactionId): JsonResponse
    {
        $order = Order::query()
            ->where('transaction_id', $transactionId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $order) return $this->error('Transaction not found.', 404);

        try {
            return $this->success($this->payWay->check($transactionId));
        } catch (RuntimeException $exception) {
            Log::error('ABA PayWay status check failed', ['transaction_id' => $transactionId, 'message' => $exception->getMessage()]);
            return $this->error('Unable to check the ABA PayWay transaction.', 502);
        }
    }

    public function callback(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $signature = $request->header('X-PayWay-Hmac-Sha512');

        if (! $this->payWay->verifyCallback($payload, $signature)) {
            return $this->error('Invalid PayWay callback signature.', 401);
        }

        $returnParams = json_decode((string) ($payload['return_params'] ?? '{}'), true) ?: [];
        $order = isset($returnParams['order_id']) ? Order::find($returnParams['order_id']) : null;
        if (! $order) return $this->error('Order not found.', 404);

        $status = (string) ($payload['status'] ?? '');
        if ($status === '0') {
            $order->update(['payment_status' => 'paid', 'paid_at' => now()]);
        } elseif (in_array($status, ['200', '201'], true)) {
            $order->update(['payment_status' => 'failed']);
        }

        return $this->success(['order_id' => $order->id], 'PayWay callback accepted.');
    }
}
