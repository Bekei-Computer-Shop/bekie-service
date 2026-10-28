<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AbaPayWayException;
use App\Services\AbaPayWayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class AbaPayWayServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'aba_payway.merchant_id' => 'test-merchant',
            'aba_payway.api_key' => 'test-api-key',
            'aba_payway.purchase_url' => 'https://payway.example.test/purchase',
            'aba_payway.return_url' => 'https://shop.example.test/api/v1/payments/aba/callback',
        ]);
    }

    public function test_khqr_deeplink_purchase_uses_documented_json_response(): void
    {
        $this->travelTo(Carbon::parse('2026-01-02 03:04:05', 'UTC'));

        Http::fake([
            'https://payway.example.test/purchase' => Http::response([
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'ORDER123'],
                'qr_string' => 'SANDBOX-QR-PAYLOAD-TEST',
                'abapay_deeplink' => 'abamobilebank://ababank.com?type=payway&qrcode=SANDBOX-QR-PAYLOAD-TEST',
                'checkout_qr_url' => 'https://payway.example.test/qr/transaction',
            ]),
        ]);

        $payment = app(AbaPayWayService::class)->purchase([
            'tran_id' => 'ORDER123',
            'amount' => 125.50,
            'currency' => 'USD',
            'payment_option' => 'abapay_khqr_deeplink',
            'view_type' => 'hosted_view',
            'return_url' => 'https://shop.example.test/checkout',
        ]);

        $this->assertSame('https://payway.example.test/qr/transaction', $payment['gateway']['checkout_qr_url']);
        $this->assertSame('https://payway.example.test/qr/transaction', $payment['checkout_url']);
        $this->assertSame('qr_string', $payment['payment_payload']['type']);
        $this->assertSame('SANDBOX-QR-PAYLOAD-TEST', $payment['payment_payload']['value']);
        $this->assertSame('SANDBOX-QR-PAYLOAD-TEST', $payment['qr_string']);
        $this->assertSame('abamobilebank://ababank.com?type=payway&qrcode=SANDBOX-QR-PAYLOAD-TEST', $payment['abapay_deeplink']);
        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return $request->isMultipart()
                && str_contains($body, 'abapay_khqr_deeplink')
                && str_contains($body, 'popup')
                && str_contains($body, '125.50')
                && str_contains($body, 'hash')
                && $this->multipartField($request, 'req_time') === '20260102030405'
                && $this->multipartField($request, 'return_url') === base64_encode(config('aba_payway.return_url'))
                && $this->multipartField($request, 'payment_option') === 'abapay_khqr_deeplink'
                && $this->multipartField($request, 'hash') !== null;
        });
    }

    public function test_purchase_maps_observed_camel_case_qr_string_field(): void
    {
        Http::fake([
            'https://payway.example.test/purchase' => Http::response([
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'ORDER123'],
                'qrString' => 'SANDBOX-QR-PAYLOAD-TEST',
                'abapay_deeplink' => 'abamobilebank://ababank.com?type=payway&qrcode=SANDBOX-QR-PAYLOAD-TEST',
            ]),
        ]);

        $payment = $this->purchase();

        $this->assertSame('qr_string', $payment['payment_payload']['type']);
        $this->assertSame('SANDBOX-QR-PAYLOAD-TEST', $payment['payment_payload']['value']);
    }

    public function test_purchase_rejects_success_response_without_documented_qr_payload(): void
    {
        Http::fake([
            'https://payway.example.test/purchase' => Http::response([
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'ORDER123'],
                'secret_data' => 'DO-NOT-LOG',
            ]),
        ]);
        Log::spy();

        try {
            $this->purchase();
            $this->fail('Expected missing QR payload to be rejected.');
        } catch (AbaPayWayException $exception) {
            $this->assertSame('qr_payload_missing', $exception->errors['aba']['reason_code']);
        }

        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context): bool {
            return $message === 'ABA PayWay purchase rejected'
                && $context['reason_code'] === 'qr_payload_missing'
                && $context['response_field_types']['status.code'] === 'string'
                && $context['response_field_types']['status.tran_id'] === 'string'
                && $context['response_field_types']['qr_string'] === 'missing'
                && $context['response_field_types']['qrString'] === 'missing'
                && ! in_array('DO-NOT-LOG', $context, true);
        })->once();
    }

    public function test_purchase_reports_transaction_id_mismatch_separately(): void
    {
        Http::fake([
            'https://payway.example.test/purchase' => Http::response([
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'OTHER-TRANSACTION'],
                'qr_string' => 'SANDBOX-QR-PAYLOAD-TEST',
            ]),
        ]);

        try {
            $this->purchase();
            $this->fail('Expected mismatched transaction id to be rejected.');
        } catch (AbaPayWayException $exception) {
            $this->assertSame('transaction_id_mismatch', $exception->errors['aba']['reason_code']);
        }
    }

    public function test_purchase_omits_non_https_checkout_url_but_keeps_documented_qr_payload(): void
    {
        Http::fake([
            'https://payway.example.test/purchase' => Http::response([
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'ORDER123'],
                'qr_string' => 'SANDBOX-QR-PAYLOAD-TEST',
                'checkout_qr_url' => 'http://payway.example.test/not-https',
            ]),
        ]);

        $payment = $this->purchase();

        $this->assertSame('SANDBOX-QR-PAYLOAD-TEST', $payment['payment_payload']['value']);
        $this->assertNull($payment['checkout_url']);
        $this->assertArrayNotHasKey('checkout_qr_url', $payment['gateway']);
    }

    public function test_purchase_rejects_non_json_checkout_response(): void
    {
        Http::fake([
            'https://payway.example.test/purchase' => Http::response(
                '<!doctype html><title>PayWay Checkout</title>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            ),
        ]);

        $this->expectException(RuntimeException::class);

        $this->purchase();
    }

    public function test_purchase_rejects_non_success_http_status(): void
    {
        Http::fake([
            'https://payway.example.test/purchase' => Http::response([
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'ORDER123'],
                'qr_string' => 'SANDBOX-QR-PAYLOAD-TEST',
            ], 503),
        ]);

        $this->expectException(RuntimeException::class);

        $this->purchase();
    }

    public function test_purchase_rejects_failed_status_and_logs_only_safe_metadata(): void
    {
        Http::fake([
            'https://payway.example.test/purchase' => Http::response([
                'status' => ['code' => '81', 'message' => 'Request rejected.'],
                'message' => 'Do not log this response value.',
            ]),
        ]);
        Log::spy();

        try {
            $this->purchase();
            $this->fail('Expected PayWay purchase rejection.');
        } catch (RuntimeException $exception) {
            $this->assertSame('ABA PayWay rejected the checkout request.', $exception->getMessage());
        }

        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context): bool {
            return $message === 'ABA PayWay purchase rejected'
                && $context['tran_id'] === 'ORDER123'
                && $context['http_status'] === 200
                && $context['status_code'] === '81'
                && $context['response_keys'] === ['status', 'message']
                && $context['content_type'] === 'application/json'
                && ! in_array('Do not log this response value.', $context, true);
        })->once();
    }

    private function purchase(): array
    {
        return app(AbaPayWayService::class)->purchase([
            'tran_id' => 'ORDER123',
            'amount' => 125.50,
            'currency' => 'USD',
            'payment_option' => 'abapay_khqr_deeplink',
            'view_type' => 'hosted_view',
            'return_url' => 'https://shop.example.test/checkout',
        ]);
    }

    private function multipartField(Request $request, string $name): ?string
    {
        preg_match('/name="'.preg_quote($name, '/').'"\r\n(?:Content-Length: \d+\r\n)?\r\n([^\r\n]*)/', $request->body(), $matches);

        return $matches[1] ?? null;
    }
}
