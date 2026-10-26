<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\ApiToken;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaxCalculationTest extends TestCase
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

    public function test_checkout_extracts_tax_from_inclusive_prices(): void
    {
        Setting::putStore('taxes.prices_include_tax', true, 'settings');
        Setting::putStore('taxes.rates', [['region' => 'Cambodia', 'rate' => 10]], 'settings');
        $cart = $this->createCart();
        $shippingMethod = ShippingMethod::factory()->active()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/cart/checkout', $this->checkoutPayload($cart, $shippingMethod));

        $response->assertCreated()
            ->assertJsonPath('data.tax_total', 9.09);

        $this->assertEquals(
            100 + $shippingMethod->calculateCost(0),
            (float) $response->json('data.grand_total'),
        );
    }

    public function test_checkout_adds_tax_to_exclusive_prices(): void
    {
        Setting::putStore('taxes.prices_include_tax', false, 'settings');
        Setting::putStore('taxes.show_tax_at_checkout', false, 'settings');
        Setting::putStore('taxes.rates', [['region' => 'Cambodia', 'rate' => 10]], 'settings');
        $cart = $this->createCart();
        $shippingMethod = ShippingMethod::factory()->active()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/cart/checkout', $this->checkoutPayload($cart, $shippingMethod));

        $response->assertCreated();
        $this->assertEquals(10, (float) $response->json('data.tax_total'));

        $this->assertEquals(
            110 + $shippingMethod->calculateCost(0),
            (float) $response->json('data.grand_total'),
        );
    }

    public function test_order_endpoint_uses_configured_tax_settings(): void
    {
        Setting::putStore('taxes.prices_include_tax', false, 'settings');
        Setting::putStore('taxes.rates', [['region' => 'Cambodia', 'rate' => 10]], 'settings');
        $cart = $this->createCart();
        $shippingMethod = ShippingMethod::factory()->active()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/orders', $this->checkoutPayload($cart, $shippingMethod));

        $response->assertCreated();
        $this->assertEquals(10, (float) $response->json('data.tax_total'));
        $this->assertEquals(
            110 + $shippingMethod->calculateCost(0),
            (float) $response->json('data.grand_total'),
        );
    }

    public function test_cart_exposes_tax_amount_and_checkout_display_settings(): void
    {
        Setting::putStore('taxes.prices_include_tax', false, 'settings');
        Setting::putStore('taxes.show_tax_at_checkout', false, 'settings');
        Setting::putStore('taxes.rates', [['region' => 'Cambodia', 'rate' => 10]], 'settings');
        $cart = $this->createCart();

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->getJson('/api/v1/cart');

        $response->assertOk()
            ->assertJsonPath('data.prices_include_tax', false)
            ->assertJsonPath('data.show_tax_at_checkout', false);
        $this->assertEquals(10, (float) $response->json('data.tax_total'));
    }

    public function test_tax_settings_endpoint_returns_checkout_only_configuration(): void
    {
        Setting::putStore('taxes.prices_include_tax', true, 'settings');
        Setting::putStore('taxes.show_tax_at_checkout', false, 'settings');
        Setting::putStore('taxes.rates', [[
            'region' => 'Cambodia',
            'name' => 'Cambodia VAT',
            'rate' => 10,
        ]], 'settings');

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->getJson('/api/v1/tax-settings');

        $response->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.hide_tax', true)
            ->assertJsonPath('data.rate', 10)
            ->assertJsonPath('data.calculation_type', 'inclusive')
            ->assertJsonPath('data.label', 'Cambodia VAT');
        $this->assertEqualsCanonicalizing(
            ['enabled', 'hide_tax', 'rate', 'calculation_type', 'label'],
            array_keys($response->json('data')),
        );
    }

    public function test_tax_settings_are_disabled_when_region_rate_is_zero(): void
    {
        Setting::putStore('taxes.rates', [[
            'region' => 'Cambodia',
            'name' => 'No VAT',
            'rate' => 0,
        ]], 'settings');

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->getJson('/api/v1/tax-settings');

        $response->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.rate', 0);
    }

    private function createCart(): Cart
    {
        $product = Product::factory()->create(['price' => 100, 'weight' => 0]);
        $cart = Cart::factory()->for($this->user)->create([
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 0,
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'sale_price' => 100,
            'cost_price' => 50,
            'subtotal' => 100,
            'discount' => 0,
            'total' => 100,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
        ]);

        return $cart;
    }

    private function checkoutPayload(Cart $cart, ShippingMethod $shippingMethod): array
    {
        return [
            'cart_id' => $cart->id,
            'shipping_method_id' => $shippingMethod->id,
            'recipient_name' => 'John Doe',
            'phone' => '+85512345678',
            'email' => 'customer@example.com',
            'address_line_1' => '123 Main St',
            'city' => 'Phnom Penh',
            'country' => 'Cambodia',
        ];
    }
}
