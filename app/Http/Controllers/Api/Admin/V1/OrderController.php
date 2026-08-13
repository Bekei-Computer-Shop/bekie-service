<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\IndexOrdersRequest;
use App\Http\Requests\Api\Admin\V1\StoreOrderRequest;
use App\Http\Requests\Api\Admin\V1\UpdateOrderRequest;
use App\Http\Resources\Api\Admin\V1\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends BaseAdminController
{
    public function index(IndexOrdersRequest $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');

        // Filters that always apply, whichever aggregate we are building.
        $base = fn () => Order::query()
            ->when($request->filled('q'), function ($query) use ($request): void {
                $like = '%'.$request->input('q').'%';
                $query->where(function ($q) use ($like): void {
                    $q->where('order_number', 'like', $like)
                        // `users.name` does not exist — User::getNameAttribute()
                        // composes it from first_name/last_name, so search the
                        // real columns plus their concatenation for full names.
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('username', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhereRaw($this->fullNameExpression().' like ?', [$like]))
                        ->orWhereHas('items', fn ($i) => $i->where('product_name', 'like', $like));
                });
            })
            ->when($request->filled('customer_id'), fn ($query) => $query->where('user_id', (int) $request->input('customer_id')));

        $withDates = fn () => $base()
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('date_to')));

        $query = $withDates()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')));

        $total = (clone $query)->count();
        $items = $query->with(['user', 'items.product'])
            ->orderBy($sort, $direction)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->success([
            'items' => OrderResource::collection($items)->resolve($request),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'count' => $items->count(),
            ],
            'filters' => [
                // Counts ignore the status filter so each status tab can show its
                // own total; they still honour the date range and search.
                'status_counts' => $withDates()
                    ->select('status', DB::raw('count(*) as aggregate'))
                    ->groupBy('status')
                    ->pluck('aggregate', 'status'),
                // Months that have orders at all, so the month filter can list
                // real options instead of guessing from the current page.
                'months' => $base()
                    ->selectRaw($this->monthExpression().' as month')
                    ->distinct()
                    ->orderByDesc('month')
                    ->pluck('month'),
            ],
        ]);
    }

    /**
     * "first last" for matching a full name typed into the search box.
     */
    private function fullNameExpression(): string
    {
        $concat = "coalesce(first_name, '') || ' ' || coalesce(last_name, '')";

        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "concat(coalesce(first_name, ''), ' ', coalesce(last_name, ''))",
            default => $concat,
        };
    }

    /**
     * 'YYYY-MM' month bucket for the current driver. Production runs Postgres
     * but the test suite runs SQLite, so this cannot be hardcoded.
     */
    private function monthExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "to_char(created_at, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', created_at)",
            'sqlsrv' => "format(created_at, 'yyyy-MM')",
            default => "date_format(created_at, '%Y-%m')",
        };
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['user', 'items.product']);

        return $this->success(new OrderResource($order));
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $discount = round((float) ($validated['discount_total'] ?? 0), 2);
        $tax = round((float) ($validated['tax_total'] ?? 0), 2);
        $shipping = round((float) ($validated['shipping_total'] ?? 0), 2);

        // Capture the customer's delivery address at creation time so the order
        // keeps it even if their address changes later.
        $customer = User::with('defaultAddress')->findOrFail($validated['customer_id']);
        $address = $customer->defaultAddress;

        // Wrapped so a bad line item cannot leave a half-built order behind.
        $order = DB::transaction(function () use ($validated, $discount, $tax, $shipping, $customer, $address) {
            $order = Order::create([
                'order_number' => Str::upper(Str::random(10)),
                'user_id' => $validated['customer_id'],
                'address_id' => $address?->id,
                'customer_snapshot' => [
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ],
                'address_snapshot' => $address ? [
                    'label' => $address->label,
                    'full_name' => $address->full_name,
                    'phone' => $address->phone,
                    'address_line_1' => $address->address_line_1,
                    'address_line_2' => $address->address_line_2,
                    'city' => $address->city,
                    'state' => $address->state,
                    'postal_code' => $address->postal_code,
                    'country' => $address->country,
                ] : null,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
                'currency' => $validated['currency'] ?? 'USD',
                'payment_method' => $validated['payment_method'] ?? null,
                'payment_status' => $validated['payment_status'] ?? 'pending',
                'transaction_id' => $validated['transaction_id'] ?? null,
                'subtotal' => 0,
                'discount_total' => $discount,
                'tax_total' => $tax,
                'shipping_total' => $shipping,
                'grand_total' => 0,
            ]);

            $items = collect($validated['items'])->map(function (array $item) use ($order) {
                $product = Product::findOrFail($item['product_id']);
                $lineTotal = round($item['qty'] * $item['unit_price'], 2);

                return $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $lineTotal,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => $lineTotal,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                ]);
            });

            // Line items make the subtotal; the order-level amounts adjust it.
            $subtotal = round($items->sum(fn ($item) => (float) $item->total), 2);
            $grandTotal = round($subtotal - $discount + $tax + $shipping, 2);

            $order->update(['subtotal' => $subtotal, 'grand_total' => max(0, $grandTotal)]);

            return $order;
        });

        return $this->created(new OrderResource($order->fresh(['user', 'items.product'])));
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        // Stamp the payment timestamps alongside the status so the two cannot
        // drift apart. Only set paid_at the first time it is marked paid.
        if (array_key_exists('payment_status', $validated)) {
            if ($validated['payment_status'] === 'paid' && ! $order->paid_at) {
                $validated['paid_at'] = now();
            }
            if ($validated['payment_status'] === 'refunded' && ! $order->refunded_at) {
                $validated['refunded_at'] = now();
            }
        }

        $order->update($validated);

        return $this->success(new OrderResource($order->fresh(['user', 'items.product'])));
    }

    public function destroy(Order $order): JsonResponse
    {
        $order->delete();

        return $this->noContent();
    }

    /**
     * Approving moves an order into the working state. It used to write
     * 'approved', which is outside the canonical set in
     * UpdateOrderRequest::STATUSES and would leave the row with no matching
     * filter tab in the admin UI.
     */
    public function approve(Order $order): JsonResponse
    {
        $order->update(['status' => 'processing']);

        return $this->success(new OrderResource($order->fresh(['user', 'items.product'])));
    }

    /**
     * Rejecting cancels the order. Previously wrote 'rejected' — see approve().
     */
    public function reject(Order $order): JsonResponse
    {
        $order->update(['status' => 'cancelled']);

        return $this->success(new OrderResource($order->fresh(['user', 'items.product'])));
    }
}
