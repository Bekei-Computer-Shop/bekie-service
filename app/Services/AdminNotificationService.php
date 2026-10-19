<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Support\Facades\DB;

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
                'product_id' => $product->id,
                'sku' => $product->sku,
                'stock_quantity' => $newQuantity,
                'min_stock_alert' => (int) $product->min_stock_alert,
            ],
        ));
    }

    /**
     * Uuids of the orders this admin has not viewed yet.
     *
     * "Unviewed" is not stored anywhere of its own: it is the admin's unread
     * "new order" notifications. Each admin gets their own row per order, so
     * the state is per admin, and reading the notification (opening the order,
     * or "Mark all as read" on the notifications page) is what clears it.
     * Orders that have since been deleted are dropped from the result.
     *
     * @return array<int, string>
     */
    public function unviewedOrderUuids(User $admin): array
    {
        $orderIds = $this->unreadOrderNotifications($admin)
            ->get()
            ->map(fn ($notification) => (int) ($notification->data['data']['order_id'] ?? 0))
            ->filter()
            ->unique()
            ->values();

        return Order::query()
            ->whereIn('id', $orderIds)
            ->orderBy('id')
            ->pluck('uuid')
            ->all();
    }

    /**
     * Marks every unread "new order" notification this admin holds for the
     * order as read. Other admins' copies are not touched.
     */
    public function markOrderViewed(User $admin, Order $order): void
    {
        $notificationIds = $this->unreadOrderNotifications($admin)
            ->get()
            ->filter(fn ($notification) => (int) ($notification->data['data']['order_id'] ?? 0) === (int) $order->id)
            ->modelKeys();

        if ($notificationIds === []) {
            return;
        }

        $admin->notifications()->whereKey($notificationIds)->update(['read_at' => now()]);
    }

    /**
     * The admin's unread notifications whose category is "orders".
     *
     * `notifications.data` is a plain `text` column, so Laravel's `->` JSON
     * path syntax does not work on it under Postgres (text has no `->>`
     * operator). The category is read with a driver-specific expression
     * instead; the order id inside the payload is matched in PHP by callers.
     */
    private function unreadOrderNotifications(User $admin)
    {
        $query = $admin->unreadNotifications();

        return match (DB::connection()->getDriverName()) {
            'pgsql' => $query->whereRaw("(data::jsonb ->> 'category') = ?", ['orders']),
            'mysql', 'mariadb' => $query->whereRaw("json_unquote(json_extract(data, '$.category')) = ?", ['orders']),
            default => $query->whereRaw("json_extract(data, '$.category') = ?", ['orders']),
        };
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
