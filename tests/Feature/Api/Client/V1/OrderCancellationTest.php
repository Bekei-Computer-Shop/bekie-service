<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\ApiToken;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $jwtToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $jti = Str::random(64);
        ApiToken::factory()->for($this->user)->create([
            'scope' => 'client',
            'token' => hash('sha256', $jti),
        ]);
        $this->jwtToken = (new JwtService)->encode([
            'sub' => (string) $this->user->id,
            'jti' => $jti,
            'scope' => 'client',
        ], 60 * 24 * 60 * 60);
    }

    public function test_customer_cancels_pending_unpaid_order_and_inventory_is_restored(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 8,
            'track_inventory' => true,
        ]);
        $order = $this->createOrder($this->user, $product);
        $serial = ProductSerial::create([
            'product_id' => $product->id,
            'serial_number' => 'CANCEL-ORDER-001',
            'status' => ProductSerial::RESERVED,
            'customer_id' => $this->user->id,
            'order_id' => $order->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson("/api/v1/orders/{$order->uuid}/cancel");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.tracking.status', 'cancelled');

        expect($order->fresh()->cancelled_at)->not->toBeNull()
            ->and($product->fresh()->stock_quantity)->toBe(10)
            ->and($serial->fresh()->status)->toBe(ProductSerial::AVAILABLE)
            ->and($serial->fresh()->order_id)->toBeNull()
            ->and($serial->fresh()->customer_id)->toBeNull();
    }

    public function test_paid_or_processing_orders_cannot_be_cancelled_by_customer(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 8, 'track_inventory' => true]);
        $paidOrder = $this->createOrder($this->user, $product, paymentStatus: 'paid');
        $processingOrder = $this->createOrder($this->user, $product, status: 'processing');

        foreach ([$paidOrder, $processingOrder] as $order) {
            $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
                ->postJson("/api/v1/orders/{$order->uuid}/cancel")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['order']);
        }

        expect($paidOrder->fresh()->status)->toBe('pending')
            ->and($processingOrder->fresh()->status)->toBe('processing')
            ->and($product->fresh()->stock_quantity)->toBe(8);
    }

    public function test_customer_cannot_cancel_another_customers_order(): void
    {
        $otherUser = User::factory()->create();
        $order = $this->createOrder($otherUser);

        $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson("/api/v1/orders/{$order->uuid}/cancel")
            ->assertNotFound();

        expect($order->fresh()->status)->toBe('pending');
    }

    public function test_order_cancellation_requires_authentication(): void
    {
        $order = $this->createOrder($this->user);

        $this->postJson("/api/v1/orders/{$order->uuid}/cancel")->assertUnauthorized();
    }

    private function createOrder(
        User $customer,
        ?Product $product = null,
        string $status = 'pending',
        string $paymentStatus = 'pending',
    ): Order {
        static $sequence = 0;
        $sequence++;

        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'CANCEL-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'currency' => 'USD',
            'payment_method' => 'cod',
            'payment_status' => $paymentStatus,
            'status' => $status,
            'shipping_status' => 'pending',
            'subtotal' => 20,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 20,
        ]);

        if ($product) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 10,
                'sale_price' => 10,
                'cost_price' => 5,
                'subtotal' => 20,
                'discount' => 0,
                'tax' => 0,
                'total' => 20,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity_shipped' => 0,
                'quantity_refunded' => 0,
                'status' => 'pending',
            ]);
        }

        return $order;
    }
}
