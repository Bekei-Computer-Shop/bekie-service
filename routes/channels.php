<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});

Broadcast::channel('order.{orderId}', function (User $user, int $orderId): bool {
    $order = Order::query()->find($orderId);

    return $order && ($user->id === $order->user_id || $user->is_admin);
});

Broadcast::channel('admin', function (User $user): bool {
    return $user->is_admin;
});
