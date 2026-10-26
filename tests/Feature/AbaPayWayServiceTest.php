<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AbaPayWayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AbaPayWayServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.payway.merchant_id' => 'test-merchant',
            'services.payway.api_key' => 'test-api-key',
            'services.payway.purchase_url' => 'https://payway.example.test/purchase',
            'services.payway.return_url' => 'https://shop.example.test/api/v1/payments/aba/callback',
        ]);
    }

    public function test_khqr_purchase_uses_payway_and_returns_its_qr_url(): void
    {
        $this->travelTo(Carbon::parse('2026-01-02 03:04:05', 'UTC'));

        Http::fake([
            'https://payway.example.test/purchase' => Http::response([
                'status' => ['code' => '00', 'message' => 'Success!'],
                'checkout_qr_url' => 'https://payway.example.test/qr/transaction',
            ]),
        ]);

        $payment = app(AbaPayWayService::class)->purchase([
            'tran_id' => 'ORDER123',
            'amount' => 125.50,
            'currency' => 'USD',
            'payment_option' => 'abapay_khqr',
            'view_type' => 'hosted_view',
            'return_url' => 'https://shop.example.test/checkout',
        ]);

        $this->assertSame('https://payway.example.test/qr/transaction', $payment['gateway']['checkout_qr_url']);
        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return $request->isMultipart()
                && str_contains($body, 'abapay_khqr')
                && str_contains($body, 'popup')
                && str_contains($body, '125.50')
                && str_contains($body, 'hash')
                && $this->multipartField($request, 'req_time') === '20260102030405'
                && $this->multipartField($request, 'return_url') === base64_encode(config('services.payway.return_url'))
                && $this->multipartField($request, 'hash') === 'EpMVN1tekUoSuxNNsgDplhu+uo41AX/J2L2wNF0bNc2kZh/wqwsRBIR92zdItpbaeGsgB65x6LQLD02i3+8PbQ==';
        });
    }

    public function test_khqr_purchase_rejects_gateway_response_without_qr_url(): void
    {
        Http::fake([
            'https://payway.example.test/purchase' => Http::response([
                'status' => ['code' => '00', 'message' => 'Success!'],
            ]),
        ]);

        $this->expectException(RuntimeException::class);

        app(AbaPayWayService::class)->purchase([
            'tran_id' => 'ORDER123',
            'amount' => 125.50,
            'currency' => 'USD',
            'payment_option' => 'abapay_khqr',
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
