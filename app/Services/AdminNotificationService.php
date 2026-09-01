<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\AdminNotification;

class AdminNotificationService
{
    public function newOrder(Order $order, string $source): void
    {
        $this->notifyAdmins(new AdminNotification(
            'orders',
            'New order received',
            sprintf('Order %s was placed from the %s.', $order->order_number, $source),
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'source' => $source,
                'total' => (float) $order->grand_total,
            ],
        ));
    }

    public function newCustomer(User $customer): void
    {
        $this->notifyAdmins(new AdminNotification(
            'customers',
            'New customer registered',
            sprintf('%s created a customer account.', $customer->name ?: $customer->email),
            [
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
            ],
        ));
    }

    public function inventoryStatus(Product $product, int $previousQuantity, int $newQuantity): void
    {
        $crossedIntoOut = $newQuantity <= 0 && $previousQuantity > 0;
        $crossedIntoLow = $newQuantity > 0
            && $newQuantity <= (int) $product->min_stock_alert
            && ($previousQuantity > (int) $product->min_stock_alert || $previousQuantity <= 0);

        if (! $crossedIntoOut && ! $crossedIntoLow) {
            return;
        }

        $category = $crossedIntoOut ? 'out_of_stock' : 'low_stock';
        $this->notifyAdmins(new AdminNotification(
            $category,
            $crossedIntoOut ? 'Product out of stock' : 'Product stock is low',
            sprintf('%s (%s) now has %d unit%s remaining.', $product->name, $product->sku, $newQuantity, $newQuantity === 1 ? '' : 's'),
            [
                'product_id' => $product->uuid,
                'sku' => $product->sku,
                'stock_quantity' => $newQuantity,
                'min_stock_alert' => (int) $product->min_stock_alert,
            ],
        ));
    }

    private function notifyAdmins(AdminNotification $notification): void
    {
        User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->where('is_banned', false)
            ->get()
            ->each(fn (User $admin) => $admin->notify($notification));
    }
}
