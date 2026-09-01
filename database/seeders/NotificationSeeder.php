<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    private const PER_TAB = 12;

    public function run(): void
    {
        $admins = User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->where('is_banned', false)
            ->get();

        if ($admins->isEmpty()) {
            $this->command?->warn('No active admin users found; notifications were not seeded.');

            return;
        }

        $products = Product::query()->get(['uuid', 'name', 'sku', 'stock_quantity', 'min_stock_alert']);
        $orders = Order::query()->latest()->limit(self::PER_TAB)->get(['id', 'order_number', 'grand_total']);
        $customers = User::query()
            ->where('is_admin', false)
            ->latest()
            ->limit(self::PER_TAB)
            ->get(['id', 'first_name', 'last_name', 'email']);

        DB::transaction(function () use ($admins, $products, $orders, $customers): void {
            foreach ($admins as $admin) {
                DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->where('notifiable_id', $admin->id)
                    ->where('type', 'App\\Notifications\\AdminNotification')
                    ->whereRaw("data::jsonb -> 'data' ->> 'seeded' = 'true'")
                    ->delete();

                $rows = array_merge(
                    $this->orderNotifications($orders),
                    $this->customerNotifications($customers),
                    $this->inventoryNotifications($products, false),
                    $this->inventoryNotifications($products, true),
                );

                foreach ($rows as $index => $row) {
                    $createdAt = Carbon::now()->subMinutes($index * 37 + 5);
                    DB::table('notifications')->insert([
                        'id' => (string) Str::uuid(),
                        'type' => 'App\\Notifications\\AdminNotification',
                        'notifiable_type' => User::class,
                        'notifiable_id' => $admin->id,
                        'data' => json_encode([
                            'category' => $row['category'],
                            'title' => $row['title'],
                            'message' => $row['message'],
                            'data' => array_merge($row['data'], ['seeded' => true]),
                        ], JSON_THROW_ON_ERROR),
                        'read_at' => $index % 4 === 3 ? $createdAt->copy()->addMinutes(8) : null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }
            }
        });

        $this->command?->info(sprintf('Seeded %d notifications per active admin.', count($this->notificationRows($orders, $customers, $products))));
    }

    private function orderNotifications(iterable $orders): array
    {
        $orders = collect($orders);

        return collect(range(1, self::PER_TAB))->map(function (int $number) use ($orders): array {
            $order = $orders->get(($number - 1) % max(1, $orders->count()));
            $orderNumber = $order?->order_number ?? 'ORD-DEMO-'.$number;

            return [
                'category' => 'orders',
                'title' => 'New order received',
                'message' => sprintf('Order %s was placed from the %s.', $orderNumber, $number % 2 === 0 ? 'web' : 'app'),
                'data' => [
                    'order_id' => $order?->id,
                    'order_number' => $orderNumber,
                    'source' => $number % 2 === 0 ? 'web' : 'app',
                    'total' => (float) ($order?->grand_total ?? 0),
                ],
            ];
        })->all();
    }

    private function customerNotifications(iterable $customers): array
    {
        $customers = collect($customers);

        return collect(range(1, self::PER_TAB))->map(function (int $number) use ($customers): array {
            $customer = $customers->get(($number - 1) % max(1, $customers->count()));
            $name = $customer?->name ?: $customer?->email ?: 'New customer '.$number;

            return [
                'category' => 'customers',
                'title' => 'New customer registered',
                'message' => $name.' created a customer account.',
                'data' => [
                    'customer_id' => $customer?->id,
                    'name' => $name,
                    'email' => $customer?->email,
                ],
            ];
        })->all();
    }

    private function inventoryNotifications(iterable $products, bool $outOfStock): array
    {
        $products = collect($products)->filter(function (Product $product) use ($outOfStock): bool {
            return $outOfStock
                ? (int) $product->stock_quantity <= 0
                : (int) $product->stock_quantity > 0 && (int) $product->stock_quantity <= (int) $product->min_stock_alert;
        })->values();

        return collect(range(1, self::PER_TAB))->map(function (int $number) use ($products, $outOfStock): array {
            $product = $products->get(($number - 1) % max(1, $products->count()));
            $quantity = $outOfStock ? 0 : (int) ($product?->stock_quantity ?? $number);
            $category = $outOfStock ? 'out_of_stock' : 'low_stock';

            return [
                'category' => $category,
                'title' => $outOfStock ? 'Product out of stock' : 'Product stock is low',
                'message' => sprintf('%s (%s) now has %d unit%s remaining.', $product?->name ?? 'Demo product '.$number, $product?->sku ?? 'DEMO-'.$number, $quantity, $quantity === 1 ? '' : 's'),
                'data' => [
                    'product_id' => $product?->uuid,
                    'sku' => $product?->sku ?? 'DEMO-'.$number,
                    'stock_quantity' => $quantity,
                    'min_stock_alert' => (int) ($product?->min_stock_alert ?? 5),
                ],
            ];
        })->all();
    }

    private function notificationRows(iterable $orders, iterable $customers, iterable $products): array
    {
        return array_merge(
            $this->orderNotifications($orders),
            $this->customerNotifications($customers),
            $this->inventoryNotifications($products, false),
            $this->inventoryNotifications($products, true),
        );
    }
}
