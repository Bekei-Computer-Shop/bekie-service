<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Enums\PayWayStatus;
use App\Http\Middleware\AuthenticateApiToken;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
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
            'aba_payway.merchant_id' => 'test-merchant',
            'aba_payway.api_key' => 'test-api-key',
            'aba_payway.purchase_url' => 'https://payway.example.test/purchase',
            'aba_payway.check_url' => 'https://payway.example.test/check',
            'aba_payway.return_url' => 'https://shop.example.test/api/v1/payments/aba/callback',
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
            config('aba_payway.purchase_url') => fn (Request $request) => Http::response($this->successfulPurchaseResponse($request)),
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/payments/aba/purchase', [
                'order_id' => $this->order->id,
                'return_url' => 'https://example.com/return',
                'payment_option' => 'abapay_khqr_deeplink',
            ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.view_type', 'popup')
            ->assertJsonPath('data.payment_payload.type', 'qr_string')
            ->assertJsonPath('data.payment_payload.value', 'SANDBOX-QR-PAYLOAD-TEST');
        $transactionId = $response->json('data.transaction_id');

        // Verify Payment record created
        $payment = Payment::where('provider_payment_id', $transactionId)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals('aba_payway', $payment->provider);
        $this->assertEquals($this->order->id, $payment->order_id);
        $this->assertEquals($this->customer->id, $payment->user_id);
        $this->assertSame('SANDBOX-QR-PAYLOAD-TEST', $payment->metadata['checkout']['payment_payload']['value']);
    }

    public function test_retrying_purchase_reuses_the_pending_transaction_id(): void
    {
        Http::fake([
            config('aba_payway.purchase_url') => fn (Request $request) => Http::response($this->successfulPurchaseResponse($request)),
        ]);

        $response1 = $this->actingAs($this->customer)
            ->postJson('/api/v1/payments/aba/purchase', [
                'order_id' => $this->order->id,
                'payment_option' => 'abapay_khqr_deeplink',
            ]);

        $transactionId1 = $response1->json('data.transaction_id');

        $response2 = $this->actingAs($this->customer)
            ->postJson('/api/v1/payments/aba/purchase', [
                'order_id' => $this->order->id,
                'payment_option' => 'abapay_khqr_deeplink',
            ]);

        $transactionId2 = $response2->json('data.transaction_id');

        $this->assertSame($transactionId1, $transactionId2);
        $this->assertLessThanOrEqual(20, strlen($transactionId1));
        $this->assertLessThanOrEqual(20, strlen($transactionId2));

        $this->assertCount(1, Payment::where('order_id', $this->order->id)->get());
        $transactionIds = Http::recorded()->map(fn (array $record): ?string => $this->multipartField($record[0], 'tran_id'));
        $this->assertCount(1, $transactionIds->unique());
        Http::assertSentCount(1);
    }

    public function test_retry_after_transaction_not_found_reuses_order_and_transaction(): void
    {
        $payment = Payment::create([
            'order_id' => $this->order->id,
            'user_id' => $this->customer->id,
            'payment_reference' => 'PAY-TEST-RETRY-001',
            'provider' => 'aba_payway',
            'provider_payment_id' => 'RETRY123',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'aba_payway',
            'gateway_response' => [],
        ]);
        Http::fake([
            config('aba_payway.check_url') => Http::response(['status' => ['code' => 6, 'message' => 'Transaction not found']]),
            config('aba_payway.purchase_url') => fn (Request $request) => Http::response($this->successfulPurchaseResponse($request)),
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/payments/aba/purchase', [
                'order_id' => $this->order->id,
                'payment_option' => 'abapay_khqr_deeplink',
            ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.transaction_id', 'RETRY123');
        $this->assertSame('RETRY123', $payment->fresh()->provider_payment_id);
        $this->assertCount(1, Payment::where('order_id', $this->order->id)->get());
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        Http::assertSentCount(2);
    }

    public function test_existing_provider_transaction_is_not_purchased_again(): void
    {
        Payment::create([
            'order_id' => $this->order->id,
            'user_id' => $this->customer->id,
            'payment_reference' => 'PAY-TEST-RETRY-002',
            'provider' => 'aba_payway',
            'provider_payment_id' => 'ACTIVE123',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'aba_payway',
            'gateway_response' => [],
        ]);
        Http::fake([
            config('aba_payway.check_url') => Http::response($this->checkResponse('ACTIVE123', 2, 'PENDING')),
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/payments/aba/purchase', [
                'order_id' => $this->order->id,
                'payment_option' => 'abapay_khqr_deeplink',
            ]);

        $response->assertStatus(409);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === config('aba_payway.check_url'));
    }

    public function test_concurrent_setup_request_is_rejected_by_order_lock(): void
    {
        $lock = Cache::lock('aba-payway:purchase:order:'.$this->order->id, 75);
        $this->assertTrue($lock->get());

        try {
            $response = $this->actingAs($this->customer)
                ->postJson('/api/v1/payments/aba/purchase', [
                    'order_id' => $this->order->id,
                    'payment_option' => 'abapay_khqr_deeplink',
                ]);

            $response->assertStatus(409);
            $this->assertSame('pending', $this->order->fresh()->payment_status);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_purchase_timeout_leaves_payment_and_order_pending(): void
    {
        Http::fake([
            config('aba_payway.purchase_url') => fn () => throw new ConnectionException('Simulated connection timeout.'),
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/payments/aba/purchase', [
                'order_id' => $this->order->id,
                'payment_option' => 'abapay_khqr_deeplink',
            ]);

        $response->assertStatus(502)
            ->assertJsonPath('message', 'ABA PayWay is temporarily unavailable. Check the payment status before retrying.');
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame('pending', Payment::where('order_id', $this->order->id)->sole()->status);
        $this->assertDatabaseMissing('payments', ['status' => 'paid', 'order_id' => $this->order->id]);
    }

    public function test_purchase_returns_and_logs_aba_failure_details(): void
    {
        Http::fake([
            config('aba_payway.purchase_url') => Http::response([
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
                'payment_option' => 'abapay_khqr_deeplink',
                'return_url' => 'http://127.0.0.1:8000/attacker-controlled-callback',
            ]);

        $response->assertStatus(502)
            ->assertJsonPath('errors.aba.status_code', '81')
            ->assertJsonPath('errors.aba.reason_code', 'provider_rejected')
            ->assertJsonPath('message', 'Unable to set up ABA PayWay checkout.');
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame('pending', Payment::where('order_id', $this->order->id)->sole()->status);

        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context): bool {
            return $message === 'ABA PayWay purchase rejected'
                && $context['status_code'] === '81'
                && isset($context['tran_id'], $context['http_status'], $context['response_keys'])
                && ! array_key_exists('response_body', $context)
                && ! array_key_exists('message', $context)
                && ! in_array('Return URL test-api-key is not whitelisted for test-merchant.', $context, true);
        })->once();

        Http::assertSent(fn ($request): bool => $this->multipartField($request, 'return_url') === base64_encode(config('aba_payway.return_url')));
    }

    public function test_callback_reconciles_and_marks_payment_as_paid(): void
    {
        Http::fake([
            config('aba_payway.check_url') => Http::response($this->checkResponse('TEST123', 0, 'APPROVED')),
        ]);

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
            'merchant_id' => config('aba_payway.merchant_id'),
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
        Http::assertSent(fn (Request $request): bool => $request->url() === config('aba_payway.check_url'));
    }

    public function test_callback_reconciles_and_marks_payment_as_failed(): void
    {
        Http::fake([
            config('aba_payway.check_url') => Http::response($this->checkResponse('TEST124', 201, 'DECLINED')),
        ]);

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
            'merchant_id' => config('aba_payway.merchant_id'),
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
        Http::assertNothingSent();
    }

    public function test_duplicate_callback_does_not_reconcile_or_process_twice(): void
    {
        Http::fake([
            config('aba_payway.check_url') => Http::response($this->checkResponse('TEST126', 0, 'APPROVED')),
        ]);

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
            'merchant_id' => config('aba_payway.merchant_id'),
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
        Http::assertSentCount(1);
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

    public function test_check_rejects_approved_status_without_amount_or_currency(): void
    {
        Payment::create([
            'order_id' => $this->order->id,
            'user_id' => $this->customer->id,
            'payment_reference' => 'PAY-TEST-CHECK-001',
            'provider' => 'aba_payway',
            'provider_payment_id' => 'CHECK-MISSING-AMOUNT',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'aba_payway',
            'gateway_response' => [],
        ]);
        Http::fake([
            config('aba_payway.check_url') => Http::response([
                'data' => ['payment_status_code' => 0, 'payment_status' => 'APPROVED'],
                'status' => ['code' => '00', 'message' => 'Success!'],
            ]),
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/payments/aba/CHECK-MISSING-AMOUNT');

        $response->assertStatus(502)
            ->assertJsonPath('errors.reason_code', 'check_payment_details_incomplete');
        $this->assertSame('pending', Payment::where('provider_payment_id', 'CHECK-MISSING-AMOUNT')->sole()->status);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
    }

    public function test_check_returns_reason_for_provider_rejection(): void
    {
        Payment::create([
            'order_id' => $this->order->id,
            'user_id' => $this->customer->id,
            'payment_reference' => 'PAY-TEST-CHECK-002',
            'provider' => 'aba_payway',
            'provider_payment_id' => 'CHECK-REJECTED',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'aba_payway',
            'gateway_response' => [],
        ]);
        Http::fake([
            config('aba_payway.check_url') => Http::response([
                'status' => ['code' => '6', 'message' => 'Transaction not found'],
            ]),
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/payments/aba/CHECK-REJECTED');

        $response->assertStatus(502)
            ->assertJsonPath('errors.reason_code', 'check_provider_rejected');
        $this->assertSame('pending', $this->order->fresh()->payment_status);
    }

    public function test_check_connection_failure_returns_safe_reason_and_leaves_payment_pending(): void
    {
        Payment::create([
            'order_id' => $this->order->id,
            'user_id' => $this->customer->id,
            'payment_reference' => 'PAY-TEST-CHECK-003',
            'provider' => 'aba_payway',
            'provider_payment_id' => 'CHECK-CONNECTION-FAILURE',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'aba_payway',
            'gateway_response' => [],
        ]);
        Http::fake([
            config('aba_payway.check_url') => fn () => throw new ConnectionException('Simulated check connection failure.'),
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/payments/aba/CHECK-CONNECTION-FAILURE');

        $response->assertStatus(502)
            ->assertJsonPath('errors.reason_code', 'check_connection_failure');
        $this->assertSame('pending', Payment::where('provider_payment_id', 'CHECK-CONNECTION-FAILURE')->sole()->status);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
    }

    public function test_pending_check_response_without_settled_amount_remains_pending(): void
    {
        Payment::create([
            'order_id' => $this->order->id,
            'user_id' => $this->customer->id,
            'payment_reference' => 'PAY-TEST-PENDING-001',
            'provider' => 'aba_payway',
            'provider_payment_id' => 'PENDING123',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'aba_payway',
            'gateway_response' => [],
        ]);
        Http::fake([
            config('aba_payway.check_url') => Http::response([
                'data' => [
                    'payment_status_code' => 2,
                    'payment_status' => 'PENDING',
                ],
                'status' => ['code' => '00', 'message' => 'Success!'],
            ]),
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/payments/aba/PENDING123');

        $response->assertSuccessful()
            ->assertJsonPath('data.data.payment_status_code', 2);
        $this->assertSame('pending', Payment::where('provider_payment_id', 'PENDING123')->sole()->status);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
    }

    private function generateCallbackSignature(array $payload): string
    {
        ksort($payload);
        $input = '';
        foreach ($payload as $value) {
            $input .= is_array($value) ? json_encode($value) : (string) $value;
        }

        return base64_encode(hash_hmac('sha512', $input, config('aba_payway.api_key'), true));
    }

    private function checkResponse(string $transactionId, int $statusCode, string $status): array
    {
        return [
            'status' => ['code' => '00', 'message' => 'Success!'],
            'data' => [
                'tran_id' => $transactionId,
                'payment_status_code' => $statusCode,
                'payment_status' => $status,
                'payment_amount' => '100.00',
                'payment_currency' => 'USD',
            ],
        ];
    }

    private function successfulPurchaseResponse(Request $request): array
    {
        return [
            'status' => [
                'code' => '00',
                'message' => 'Success!',
                'tran_id' => $this->multipartField($request, 'tran_id'),
            ],
            'qr_string' => 'SANDBOX-QR-PAYLOAD-TEST',
            'abapay_deeplink' => 'abamobilebank://ababank.com?type=payway&qrcode=SANDBOX-QR-PAYLOAD-TEST',
            'checkout_qr_url' => 'https://checkout-sandbox.payway.com.kh/qr/test-only',
        ];
    }

    private function multipartField(Request $request, string $name): ?string
    {
        preg_match('/name="'.preg_quote($name, '/').'"\r\n(?:Content-Length: \d+\r\n)?\r\n([^\r\n]*)/', $request->body(), $matches);

        return $matches[1] ?? null;
    }
}
