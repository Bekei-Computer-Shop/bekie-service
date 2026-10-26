<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Enums\PayWayStatus;
use App\Http\Middleware\AuthenticateApiToken;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class AbaPayWayTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(AuthenticateApiToken::class);
        config([
            'services.payway.merchant_id' => 'test-merchant',
            'services.payway.api_key' => 'test-api-key',
            'services.payway.purchase_url' => 'https://payway.example.test/purchase',
            'services.payway.return_url' => 'https://shop.example.test/api/v1/payments/aba/callback',
        ]);

        $this->customer = User::factory()->create();
        $this->order = Order::create([
            'user_id' => $this->customer->id,
            'order_number' => Str::upper(Str::random(12)),
            'currency' => 'USD',
            'payment_status' => 'pending',
            'grand_total' => 100.00,
            'shipping_total' => 10.00,
        ]);
    }

    public function test_purchase_creates_payment_record(): void
    {
        Http::fake([
            config('services.payway.purchase_url') => Http::response([
                'status' => ['code' => '00', 'message' => 'Success!'],
                'checkout_qr_url' => 'https://example.com/qr',
            ]),
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/payments/aba/purchase', [
                'order_id' => $this->order->id,
                'return_url' => 'https://example.com/return',
                'payment_option' => 'abapay_khqr',
            ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.view_type', 'popup')
            ->assertJsonPath('data.gateway.checkout_qr_url', 'https://example.com/qr');
        $transactionId = $response->json('data.transaction_id');

        // Verify Payment record created
        $payment = Payment::where('provider_payment_id', $transactionId)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals('aba_payway', $payment->provider);
        $this->assertEquals($this->order->id, $payment->order_id);
        $this->assertEquals($this->customer->id, $payment->user_id);
    }

    public function test_retrying_purchase_creates_a_new_transaction_id(): void
    {
        Http::fake([
            config('services.payway.purchase_url') => Http::response([
                'status' => ['code' => '00', 'message' => 'Success!'],
                'checkout_qr_url' => 'https://example.com/qr',
            ]),
        ]);

        $response1 = $this->actingAs($this->customer)
            ->postJson('/api/v1/payments/aba/purchase', [
                'order_id' => $this->order->id,
                'payment_option' => 'abapay_khqr',
            ]);

        $transactionId1 = $response1->json('data.transaction_id');

        $response2 = $this->actingAs($this->customer)
            ->postJson('/api/v1/payments/aba/purchase', [
                'order_id' => $this->order->id,
                'payment_option' => 'abapay_khqr',
            ]);

        $transactionId2 = $response2->json('data.transaction_id');

        $this->assertNotSame($transactionId1, $transactionId2);
        $this->assertLessThanOrEqual(20, strlen($transactionId1));
        $this->assertLessThanOrEqual(20, strlen($transactionId2));

        $this->assertCount(2, Payment::where('order_id', $this->order->id)->get());
        $transactionIds = Http::recorded()->map(fn (array $record): ?string => $this->multipartField($record[0], 'tran_id'));
        $this->assertCount(2, $transactionIds->unique());
    }

    public function test_purchase_returns_and_logs_aba_failure_details(): void
    {
        Http::fake([
            config('services.payway.purchase_url') => Http::response([
                'status' => [
                    'code' => '81',
                    'message' => 'Return URL test-api-key is not whitelisted for test-merchant.',
                ],
            ]),
        ]);
        Log::spy();

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/payments/aba/purchase', [
                'order_id' => $this->order->id,
                'payment_option' => 'abapay_khqr',
                'return_url' => 'http://127.0.0.1:8000/attacker-controlled-callback',
            ]);

        $response->assertStatus(502)
            ->assertJsonPath('errors.aba.status_code', '81')
            ->assertJsonPath('errors.aba.message', 'Return URL [REDACTED] is not whitelisted for [REDACTED].');

        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context): bool {
            return $message === 'ABA PayWay purchase rejected'
                && $context['aba_status_code'] === '81'
                && str_contains($context['response_body'], '[REDACTED]')
                && ! str_contains($context['response_body'], 'test-api-key')
                && ! str_contains($context['response_body'], 'test-merchant');
        })->once();

        Http::assertSent(fn ($request): bool => $this->multipartField($request, 'return_url') === base64_encode(config('services.payway.return_url')));
    }

    public function test_callback_marks_payment_as_paid(): void
    {
        // Create pending payment
        $payment = Payment::create([
            'order_id' => $this->order->id,
            'user_id' => $this->customer->id,
            'payment_reference' => 'PAY-TEST-001',
            'provider' => 'aba_payway',
            'provider_payment_id' => 'TEST123',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'aba_payway',
            'gateway_response' => [],
        ]);

        $payload = [
            'tran_id' => 'TEST123',
            'status' => PayWayStatus::APPROVED->value,
            'return_params' => json_encode(['order_id' => $this->order->id]),
            'merchant_id' => config('services.payway.merchant_id'),
        ];

        $signature = $this->generateCallbackSignature($payload);

        $response = $this->postJson('/api/v1/payments/aba/callback', $payload, [
            'X-PayWay-Hmac-Sha512' => $signature,
        ]);

        $response->assertSuccessful();

        // Verify payment marked as paid
        $payment->refresh();
        $this->assertEquals('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);

        // Verify order marked as paid
        $this->order->refresh();
        $this->assertEquals('paid', $this->order->payment_status);
        $this->assertNotNull($this->order->paid_at);
    }

    public function test_callback_marks_payment_as_failed(): void
    {
        $payment = Payment::create([
            'order_id' => $this->order->id,
            'user_id' => $this->customer->id,
            'payment_reference' => 'PAY-TEST-002',
            'provider' => 'aba_payway',
            'provider_payment_id' => 'TEST124',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'aba_payway',
            'gateway_response' => [],
        ]);

        $payload = [
            'tran_id' => 'TEST124',
            'status' => PayWayStatus::CANCELED->value,
            'return_params' => json_encode(['order_id' => $this->order->id]),
        ];

        $signature = $this->generateCallbackSignature($payload);

        $response = $this->postJson('/api/v1/payments/aba/callback', $payload, [
            'X-PayWay-Hmac-Sha512' => $signature,
        ]);

        $response->assertSuccessful();

        $payment->refresh();
        $this->assertEquals('failed', $payment->status);
        $this->assertNotNull($payment->failed_at);
    }

    public function test_callback_rejects_invalid_signature(): void
    {
        Payment::create([
            'order_id' => $this->order->id,
            'user_id' => $this->customer->id,
            'payment_reference' => 'PAY-TEST-003',
            'provider' => 'aba_payway',
            'provider_payment_id' => 'TEST125',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'aba_payway',
            'gateway_response' => [],
        ]);

        $payload = [
            'tran_id' => 'TEST125',
            'status' => PayWayStatus::APPROVED->value,
            'return_params' => json_encode(['order_id' => $this->order->id]),
        ];

        $response = $this->postJson('/api/v1/payments/aba/callback', $payload, [
            'X-PayWay-Hmac-Sha512' => 'invalid-signature',
        ]);

        $response->assertStatus(401);
        $this->assertEquals('Invalid PayWay callback signature.', $response->json('message'));
    }

    public function test_callback_is_idempotent(): void
    {
        $payment = Payment::create([
            'order_id' => $this->order->id,
            'user_id' => $this->customer->id,
            'payment_reference' => 'PAY-TEST-004',
            'provider' => 'aba_payway',
            'provider_payment_id' => 'TEST126',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'aba_payway',
            'gateway_response' => [],
        ]);

        $payload = [
            'tran_id' => 'TEST126',
            'status' => PayWayStatus::APPROVED->value,
            'return_params' => json_encode(['order_id' => $this->order->id]),
        ];

        $signature = $this->generateCallbackSignature($payload);

        // First callback
        $response1 = $this->postJson('/api/v1/payments/aba/callback', $payload, [
            'X-PayWay-Hmac-Sha512' => $signature,
        ]);
        $response1->assertSuccessful();

        // Store original paid_at
        $payment->refresh();
        $originalPaidAt = $payment->paid_at;

        // Duplicate callback
        $response2 = $this->postJson('/api/v1/payments/aba/callback', $payload, [
            'X-PayWay-Hmac-Sha512' => $signature,
        ]);
        $response2->assertSuccessful();

        // Verify paid_at unchanged (not updated again)
        $payment->refresh();
        $this->assertEquals($originalPaidAt->timestamp, $payment->paid_at->timestamp);
    }

    public function test_check_requires_authorization(): void
    {
        $payment = Payment::create([
            'order_id' => $this->order->id,
            'user_id' => $this->customer->id,
            'payment_reference' => 'PAY-TEST-005',
            'provider' => 'aba_payway',
            'provider_payment_id' => 'TEST127',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'aba_payway',
            'gateway_response' => [],
        ]);

        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->getJson('/api/v1/payments/aba/TEST127');

        $response->assertStatus(404);
    }

    private function generateCallbackSignature(array $payload): string
    {
        ksort($payload);
        $input = '';
        foreach ($payload as $value) {
            $input .= is_array($value) ? json_encode($value) : (string) $value;
        }

        return base64_encode(hash_hmac('sha512', $input, config('services.payway.api_key'), true));
    }

    private function multipartField(Request $request, string $name): ?string
    {
        preg_match('/name="'.preg_quote($name, '/').'"\r\n(?:Content-Length: \d+\r\n)?\r\n([^\r\n]*)/', $request->body(), $matches);

        return $matches[1] ?? null;
    }
}
