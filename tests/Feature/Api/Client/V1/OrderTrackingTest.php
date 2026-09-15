<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = ApiToken::factory()->create([
        'user_id' => $this->user->id,
        'scope' => 'client',
    ]);
});

describe('Order Tracking Status', function () {
    test('order detail includes tracking status object', function () {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'shipping_status' => 'pending',
            'tracking_number' => null,
        ]);

        $response = $this->getJson(
            "/api/v1/orders/{$order->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data'])->toHaveKey('tracking');
        expect($response['data']['tracking'])->toHaveKeys([
            'status',
            'status_label',
            'tracking_number',
            'shipping_provider',
            'events',
            'estimated_delivery',
        ]);
    });

    test('tracking status shows pending shipment', function () {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'shipping_status' => 'pending',
        ]);

        $response = $this->getJson(
            "/api/v1/orders/{$order->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['tracking']['status'])->toBe('pending');
        expect($response['data']['tracking']['status_label'])->toBe('Pending Shipment');
    });

    test('tracking status shows shipped', function () {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'shipping_status' => 'shipped',
            'tracking_number' => 'TRACK123456',
            'shipped_at' => now(),
        ]);

        $response = $this->getJson(
            "/api/v1/orders/{$order->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['tracking']['status'])->toBe('shipped');
        expect($response['data']['tracking']['status_label'])->toBe('Shipped');
        expect($response['data']['tracking']['tracking_number'])->toBe('TRACK123456');
    });

    test('tracking status shows delivered', function () {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'shipping_status' => 'delivered',
            'tracking_number' => 'TRACK123456',
            'shipped_at' => now()->subDays(3),
            'delivered_at' => now(),
        ]);

        $response = $this->getJson(
            "/api/v1/orders/{$order->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['tracking']['status'])->toBe('delivered');
        expect($response['data']['tracking']['status_label'])->toBe('Delivered');
    });

    test('tracking includes timeline events', function () {
        $createdAt = now()->subDays(5);
        $paidAt = now()->subDays(4);
        $shippedAt = now()->subDays(2);
        $deliveredAt = now();

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => $createdAt,
            'paid_at' => $paidAt,
            'shipped_at' => $shippedAt,
            'delivered_at' => $deliveredAt,
            'shipping_status' => 'delivered',
            'tracking_number' => 'TRACK123456',
        ]);

        $response = $this->getJson(
            "/api/v1/orders/{$order->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $events = $response['data']['tracking']['events'];

        expect($events)->toHaveCount(4);
        expect($events[0]['status'])->toBe('order_created');
        expect($events[0]['completed'])->toBeTrue();
        expect($events[1]['status'])->toBe('payment_confirmed');
        expect($events[1]['completed'])->toBeTrue();
        expect($events[2]['status'])->toBe('shipped');
        expect($events[2]['completed'])->toBeTrue();
        expect($events[3]['status'])->toBe('delivered');
        expect($events[3]['completed'])->toBeTrue();
    });

    test('tracking shows incomplete events', function () {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'shipping_status' => 'pending',
            'paid_at' => null,
            'shipped_at' => null,
            'delivered_at' => null,
        ]);

        $response = $this->getJson(
            "/api/v1/orders/{$order->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $events = $response['data']['tracking']['events'];

        // Order created should be complete
        expect($events[0]['completed'])->toBeTrue();

        // Payment not completed
        expect($events[1]['status'])->toBe('payment_confirmed');
        expect($events[1]['completed'])->toBeFalse();
        expect($events[1]['timestamp'])->toBeNull();

        // Shipped not completed
        expect($events[2]['status'])->toBe('shipped');
        expect($events[2]['completed'])->toBeFalse();
        expect($events[2]['timestamp'])->toBeNull();
    });

    test('estimated delivery is calculated from shipped date', function () {
        $shippedAt = now()->subDays(1);
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'shipped_at' => $shippedAt,
            'delivered_at' => null,
            'shipping_status' => 'in_transit',
        ]);

        $response = $this->getJson(
            "/api/v1/orders/{$order->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $estimatedDelivery = $response['data']['tracking']['estimated_delivery'];

        // Should be approximately 3 days from ship date
        expect($estimatedDelivery)->not->toBeNull();
    });

    test('estimated delivery is null when already delivered', function () {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'shipped_at' => now()->subDays(5),
            'delivered_at' => now(),
            'shipping_status' => 'delivered',
        ]);

        $response = $this->getJson(
            "/api/v1/orders/{$order->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['tracking']['estimated_delivery'])->toBeNull();
    });

    test('estimated delivery is null when not yet shipped', function () {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'shipped_at' => null,
            'delivered_at' => null,
            'shipping_status' => 'pending',
        ]);

        $response = $this->getJson(
            "/api/v1/orders/{$order->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['tracking']['estimated_delivery'])->toBeNull();
    });

    test('unauthorized user cannot view other user\'s order tracking', function () {
        $otherUser = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson(
            "/api/v1/orders/{$order->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertForbidden();
    });

    test('order list includes basic tracking info', function () {
        Order::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson(
            '/api/v1/orders',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data'])->toHaveKey('items');
        // Note: List view also includes tracking object now
        expect($response['data']['items'][0])->toHaveKey('tracking');
    });
});
