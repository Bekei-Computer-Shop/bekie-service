<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\AdminAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Fixtures for the admin report tests.
 *
 * Orders are written with forceFill because created_at is not mass-assignable on
 * Order/OrderItem, and the reports are all about when an order was placed — a
 * helper that silently stamped "now" on every row would make every bucketing
 * test pass for the wrong reason.
 */
final class ReportOrders
{
    private static int $sequence = 0;

    /** Bearer token for a super admin, who holds every permission. */
    public static function adminToken(): string
    {
        $user = User::factory()->superAdmin()->create();

        app()->instance('request', Request::create('/admin/auth/login', 'POST'));

        return (new AdminAuthService)->createAdminToken($user)['access_token'];
    }

    /**
     * Place an order.
     *
     * @param  string  $utcTime  When it was placed, as stored in the database (UTC).
     * @param  list<array{name?: string, product_id?: int|null, quantity?: int, total?: float, cost?: float|null, deleted?: bool}>  $items
     * @param  array{status?: string, user_id?: int|null, deleted?: bool}  $attrs
     */
    public static function place(string $utcTime, float $grandTotal, array $items = [], array $attrs = []): Order
    {
        $at = Carbon::parse($utcTime, 'UTC');

        $order = (new Order)->forceFill([
            'order_number' => 'ORD-RPT-'.++self::$sequence,
            'user_id' => $attrs['user_id'] ?? null,
            'status' => $attrs['status'] ?? 'completed',
            'grand_total' => $grandTotal,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $order->save();

        foreach ($items as $item) {
            $line = (new OrderItem)->forceFill([
                'order_id' => $order->id,
                'product_id' => $item['product_id'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['total'] ?? 0,
                'subtotal' => $item['total'] ?? 0,
                'total' => $item['total'] ?? 0,
                'cost_price' => $item['cost'] ?? null,
                'product_name' => $item['name'] ?? 'Item',
                'status' => 'completed',
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            $line->save();

            if ($item['deleted'] ?? false) {
                $line->delete();
            }
        }

        if ($attrs['deleted'] ?? false) {
            $order->delete();
        }

        return $order;
    }
}
