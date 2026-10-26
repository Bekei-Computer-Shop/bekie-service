<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\KhqrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KhqrServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_khqr_from_decimal_string_order_amount(): void
    {
        config([
            'services.khqr.enabled' => true,
            'services.khqr.merchant_id' => 'test-merchant',
            'services.khqr.merchant_name' => 'Test Merchant',
        ]);

        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-KHQR-STRING-AMOUNT',
            'grand_total' => '125.50',
            'currency' => 'USD',
        ]);

        $payment = app(KhqrService::class)->generateQrCode([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'user_email' => $user->email,
        ]);

        $this->assertSame(125.5, $payment['amount']);
        $this->assertStringContainsString(bin2hex('125.50'), $payment['qr_code_data']);
        $this->assertDatabaseHas('khqr_transactions', [
            'order_id' => $order->id,
            'amount' => '125.50',
            'status' => 'pending',
        ]);
    }
}
