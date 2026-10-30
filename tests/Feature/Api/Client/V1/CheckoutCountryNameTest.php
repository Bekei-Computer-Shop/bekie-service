<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\AuthService;
use App\Support\ShippingAddressRules;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('country names and codes normalize to ISO codes', function (string $input, string $expected): void {
    expect(ShippingAddressRules::normalizeCountry($input))->toBe($expected);
})->with([
    'code' => ['KH', 'KH'],
    'lower-case code' => [' kh ', 'KH'],
    'name' => ['Cambodia', 'KH'],
    'name in any case' => ['  cAMBODIA ', 'KH'],
    'regional name' => ['Viet Nam', 'VN'],
    'unknown stays for validation' => ['Atlantis', 'ATLANTIS'],
]);

describe('checkout', function (): void {
    beforeEach(function (): void {
        $this->seed(AdminPermissionsSeeder::class);

        $customer = User::factory()->create();
        $customer->assignRole('user');
        $token = (new AuthService)->createToken($customer, Request::create('/api/v1/auth/login', 'POST'))['access_token'];
        $this->headers = ['Authorization' => 'Bearer '.$token];

        $this->shipping = ShippingMethod::create([
            'name' => 'Pickup', 'code' => 'pickup', 'base_price' => 0, 'is_active' => true, 'type' => 'pickup',
        ]);
        $product = Product::factory()->create(['price' => 20, 'is_serialized' => false]);
        $this->cart = Cart::create(['user_id' => $customer->id, 'currency' => 'USD']);
        CartItem::create([
            'cart_id' => $this->cart->id, 'product_id' => $product->id, 'quantity' => 1,
            'unit_price' => 20, 'subtotal' => 20, 'total' => 20,
            'product_name' => $product->name, 'product_sku' => $product->sku,
        ]);
    });

    /** The body the storefront on `main` posts: the country by name. */
    $checkout = fn (string $country): array => [
        'cart_id' => test()->cart->id,
        'shipping_method_id' => test()->shipping->id,
        'recipient_name' => 'Dara Sok',
        'phone' => '012345678',
        'address_line_1' => '1 Main St',
        'city' => 'Phnom Penh',
        'postal_code' => '12000',
        'country' => $country,
        'payment_method' => 'cod',
    ];

    test('accepts the country by name and stores its code', function () use ($checkout): void {
        $this->withHeaders($this->headers)
            ->postJson('/api/v1/cart/checkout', $checkout('Cambodia'))
            ->assertCreated();

        expect(Order::firstOrFail()->address_snapshot['country'])->toBe('KH');
    });

    test('still accepts the code', function () use ($checkout): void {
        $this->withHeaders($this->headers)
            ->postJson('/api/v1/orders', $checkout('KH'))
            ->assertCreated();
    });

    test('still rejects a country it does not know', function () use ($checkout): void {
        $this->withHeaders($this->headers)
            ->postJson('/api/v1/cart/checkout', $checkout('Atlantis'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('country');
    });
});
