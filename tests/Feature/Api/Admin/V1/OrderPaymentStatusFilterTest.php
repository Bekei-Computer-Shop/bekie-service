<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

function paymentFilterAs(User $admin)
{
    app()->instance('request', Request::create('/admin/auth/login', 'POST'));
    $token = (new AdminAuthService)->createAdminToken($admin)['access_token'];

    return test()->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ]);
}

function paymentFilterOrder(string $status, string $paymentStatus): Order
{
    return Order::create([
        'order_number' => Str::upper(Str::random(10)),
        'status' => $status,
        'currency' => 'USD',
        'payment_method' => 'cod',
        'payment_status' => $paymentStatus,
        'subtotal' => 10,
        'discount_total' => 0,
        'tax_total' => 0,
        'shipping_total' => 0,
        'grand_total' => 10,
    ]);
}

test('order list filters by payment status', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $paid = paymentFilterOrder('pending', 'paid');
    paymentFilterOrder('pending', 'pending');
    paymentFilterOrder('completed', 'failed');

    $response = paymentFilterAs($admin)
        ->getJson('/api/v1/admin/orders?payment_status=paid')
        ->assertOk();

    expect($response->json('data.items'))->toHaveCount(1)
        ->and($response->json('data.items.0.order_number'))->toBe($paid->order_number)
        ->and($response->json('data.items.0.payment.status'))->toBe('paid');
});

test('payment status counts honour the status filter and status counts honour the payment filter', function (): void {
    $admin = User::factory()->superAdmin()->create();
    paymentFilterOrder('pending', 'paid');
    paymentFilterOrder('pending', 'pending');
    paymentFilterOrder('completed', 'paid');
    paymentFilterOrder('completed', 'failed');

    $byStatus = paymentFilterAs($admin)
        ->getJson('/api/v1/admin/orders?status=completed')
        ->assertOk();

    expect($byStatus->json('data.filters.payment_status_counts'))
        ->toEqual(['paid' => 1, 'failed' => 1]);

    $byPayment = paymentFilterAs($admin)
        ->getJson('/api/v1/admin/orders?payment_status=paid')
        ->assertOk();

    expect($byPayment->json('data.filters.status_counts'))
        ->toEqual(['pending' => 1, 'completed' => 1])
        ->and($byPayment->json('data.filters.payment_status_counts'))
        ->toEqual(['paid' => 2, 'pending' => 1, 'failed' => 1]);
});

test('order list rejects an unknown payment status', function (): void {
    $admin = User::factory()->superAdmin()->create();

    paymentFilterAs($admin)
        ->getJson('/api/v1/admin/orders?payment_status=bogus')
        ->assertUnprocessable();
});
