<?php

use App\Models\ContentItem;
use App\Models\Order;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Services\AuthService;
use App\Services\RealtimeNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('notification payload is small and frontend friendly', function (): void {
    $notification = new RealtimeNotification('11111111-1111-1111-1111-111111111111', 'order_tracking', 'Order Updated', 'Order is shipped.', [
        'order_id' => 10,
        'status' => 'shipped',
    ]);

    expect($notification->toArray(new AnonymousNotifiable))->toMatchArray([
        'id' => '11111111-1111-1111-1111-111111111111',
        'type' => 'order_tracking',
        'data' => ['order_id' => 10, 'status' => 'shipped'],
    ]);
});

test('order status changes notify only the order customer', function (): void {
    Notification::fake();
    $customer = User::factory()->create();
    $otherCustomer = User::factory()->create();
    $order = Order::create(['user_id' => $customer->id, 'order_number' => 'ORD-STATUS-1']);

    app(RealtimeNotificationService::class)->orderStatusChanged($order->load('user'), 'processing', 'shipped');

    Notification::assertSentTo($customer, RealtimeNotification::class);
    Notification::assertNotSentTo($otherCustomer, RealtimeNotification::class);
});

test('payment status changes notify the customer and active admins', function (): void {
    Notification::fake();
    $customer = User::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $order = Order::create(['user_id' => $customer->id, 'order_number' => 'ORD-PAYMENT-1']);

    app(RealtimeNotificationService::class)->paymentStatusChanged($order->load('user'), 'pending', 'paid');

    Notification::assertSentTo($customer, RealtimeNotification::class);
    Notification::assertSentTo($admin, RealtimeNotification::class);
});

test('published announcements honor specific user targeting', function (): void {
    Notification::fake();
    $recipient = User::factory()->create();
    $excluded = User::factory()->create();
    $announcement = ContentItem::factory()->news()->create([
        'status' => 'published',
        'audience' => ['type' => 'users', 'user_ids' => [$recipient->id]],
    ]);

    app(RealtimeNotificationService::class)->announcementPublished($announcement);

    Notification::assertSentTo($recipient, RealtimeNotification::class);
    Notification::assertNotSentTo($excluded, RealtimeNotification::class);
});

test('client users can authorize only their own private notification channel', function (): void {
    putenv('APP_KEY=base64:realtime-notification-test-key');
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = app(AuthService::class)->createToken($user, request())['access_token'];

    $this->withToken($token)
        ->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-user.'.$user->id,
        ])
        ->assertOk();

    $this->withToken($token)
        ->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-user.'.$otherUser->id,
        ])
        ->assertForbidden();
});
test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
