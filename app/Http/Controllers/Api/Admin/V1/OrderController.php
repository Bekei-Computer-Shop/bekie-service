<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\IndexOrdersRequest;
use App\Http\Requests\Api\Admin\V1\PreviewOrderRequest;
use App\Http\Requests\Api\Admin\V1\StoreOrderRequest;
use App\Http\Requests\Api\Admin\V1\UpdateOrderRequest;
use App\Http\Resources\Api\Admin\V1\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Services\ProductPromotionService;
use App\Services\ProductSerialService;
use App\Services\TaxCalculator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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
        $items = $query->with(['user', 'items.product', 'coupon'])
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

    /**
     * Orders this admin has not opened yet, for the sidebar badge and the
     * "New" marker in the orders table. Per admin: see AdminNotificationService.
     */
    public function unviewed(Request $request): JsonResponse
    {
        return $this->success($this->unviewedPayload($request));
    }

    /**
     * Called when an admin opens an order. Answers with the refreshed list so
     * the client can drop the order without knowing which id (uuid or numeric)
     * the page was opened with.
     */
    public function viewed(Request $request, Order $order): JsonResponse
    {
        app(AdminNotificationService::class)->markOrderViewed($request->user(), $order);

        return $this->success($this->unviewedPayload($request));
    }

    /** @return array{count: int, order_ids: array<int, string>} */
    private function unviewedPayload(Request $request): array
    {
        $ids = app(AdminNotificationService::class)->unviewedOrderUuids($request->user());

        return ['count' => count($ids), 'order_ids' => $ids];
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['user.defaultAddress', 'items.product', 'coupon']);

        return $this->success(new OrderResource($order));
    }

    /**
     * Prices an order without saving it: what each line costs once the
     * promotions on its product are taken off. The create-order form calls this
     * while the admin builds the order so the total it shows is the one store()
     * will save. Writes nothing — no order, no promotion usage.
     */
    public function preview(
        PreviewOrderRequest $request,
        ProductPromotionService $promotions,
        TaxCalculator $taxCalculator,
    ): JsonResponse {
        $validated = $request->validated();
        $customer = isset($validated['customer_id'])
            ? User::with('defaultAddress')->findOrFail($validated['customer_id'])
            : null;

        $priced = $promotions->priceLines(
            $this->promotionLines($validated['items']),
            isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
        );

        $subtotal = round((float) $priced->sum('subtotal'), 2);
        $promotionDiscount = round((float) $priced->sum('discount'), 2);
        $tax = $taxCalculator->calculate(
            $subtotal,
            $promotionDiscount,
            $customer?->defaultAddress?->country ?? 'Cambodia',
        );

        return $this->success([
            'items' => $priced->map(fn (array $line, int $index): array => [
                'product_id' => $validated['items'][$index]['product_id'],
                'subtotal' => number_format($line['subtotal'], 2, '.', ''),
                'discount' => number_format($line['discount'], 2, '.', ''),
                'total' => number_format($line['total'], 2, '.', ''),
                'promotion' => $promotions->snapshot($line),
                'discount_source' => $line['discount_source'],
            ])->values()->all(),
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'promotion_discount' => number_format($promotionDiscount, 2, '.', ''),
            'tax_total' => number_format($tax['tax_total'], 2, '.', ''),
            'tax_settings' => $tax['tax_settings'],
            'total' => number_format($tax['grand_total'], 2, '.', ''),
        ]);
    }

    public function store(StoreOrderRequest $request, ProductPromotionService $promotions): JsonResponse
    {
        $validated = $request->validated();

        $discount = round((float) ($validated['discount_total'] ?? 0), 2);
        $shipping = round((float) ($validated['shipping_total'] ?? 0), 2);

        // Capture the customer's delivery address at creation time so the order
        // keeps it even if their address changes later.
        $customer = User::with('defaultAddress')->findOrFail($validated['customer_id']);
        $address = $customer->defaultAddress;

        // Wrapped so a bad line item cannot leave a half-built order behind.
        $order = DB::transaction(function () use ($validated, $discount, $shipping, $customer, $address, $promotions) {
            // What the promotions on each product take off its line. Priced
            // inside the transaction so the usage it records commits with the
            // order or not at all.
            $lines = $this->promotionLines($validated['items']);
            $priced = $promotions->priceLines($lines, (int) $customer->id);
            $promotionDiscount = round((float) $priced->sum('discount'), 2);
            $subtotal = round((float) $priced->sum('subtotal'), 2);
            $tax = app(TaxCalculator::class)->calculate(
                $subtotal,
                $discount + $promotionDiscount,
                $address?->country ?? 'Cambodia',
            );

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
                'discount_total' => round($discount + $promotionDiscount, 2),
                'tax_total' => $tax['tax_total'],
                'shipping_total' => $shipping,
                'grand_total' => 0,
            ]);

            foreach ($lines as $index => $line) {
                $product = $line['product'];

                // `subtotal` is the line before any promotion, `discount` what
                // the promotion took off and `total` what the line charges.
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'subtotal' => $priced[$index]['subtotal'],
                    'discount' => $priced[$index]['discount'],
                    'tax' => 0,
                    'total' => $priced[$index]['total'],
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'metadata' => ['promotion' => $promotions->snapshot($priced[$index])],
                ]);
            }

            // Line items make the subtotal, before promotions; the discount
            // total is the promotions plus any amount keyed in on the order.
            $grandTotal = round($tax['grand_total'] + $shipping, 2);

            $order->update(['subtotal' => $subtotal, 'grand_total' => max(0, $grandTotal)]);

            $promotions->recordUsage($order, $priced);

            return $order;
        });

        app(AdminNotificationService::class)->newOrder($order, 'web');

        if ($request->filled('serial_numbers')) {
            app(ProductSerialService::class)->reserveForOrder($request->input('serial_numbers'), (int) $order->user_id, (int) $order->id, (int) $request->user()->id);
        }

        return $this->created(new OrderResource($order->fresh(['user', 'items.product', 'coupon'])));
    }

    /**
     * The submitted line items as the promotion service wants them, each with
     * its product (and that product's promotions) loaded. Keyed by position in
     * the request. A product that is missing or deleted is a 404, as it was
     * when each line looked its product up on its own.
     *
     * @param  list<array{product_id: string, qty: int|string, unit_price: float|int|string}>  $items
     * @return Collection<int, array{product: Product, quantity: int, unit_price: float}>
     */
    private function promotionLines(array $items): Collection
    {
        $products = Product::query()
            ->with('promotions')
            ->findMany(collect($items)->pluck('product_id')->unique()->all())
            ->keyBy('id');

        return collect($items)->map(fn (array $item): array => [
            'product' => $products->get($item['product_id'])
                ?? throw (new ModelNotFoundException)->setModel(Product::class, [$item['product_id']]),
            'quantity' => (int) $item['qty'],
            'unit_price' => (float) $item['unit_price'],
            'variant_id' => isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : null,
        ]);
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

        $previousStatus = $order->status;
        $order->update($validated);

        if (($validated['status'] ?? null) === 'completed' && $previousStatus !== 'completed') {
            app(ProductSerialService::class)->finalizeOrder((int) $order->id, (int) $request->user()->id);
        }
        if (($validated['status'] ?? null) === 'cancelled' && $previousStatus !== 'cancelled') {
            app(ProductSerialService::class)->releaseOrder((int) $order->id, (int) $request->user()->id, 'Order cancelled.');
        }

        return $this->success(new OrderResource($order->fresh(['user', 'items.product', 'coupon'])));
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

        return $this->success(new OrderResource($order->fresh(['user', 'items.product', 'coupon'])));
    }

    /**
     * Rejecting cancels the order. Previously wrote 'rejected' — see approve().
     */
    public function reject(Order $order): JsonResponse
    {
        $order->update(['status' => 'cancelled']);
        app(ProductSerialService::class)->releaseOrder((int) $order->id, null, 'Order rejected.');

        return $this->success(new OrderResource($order->fresh(['user', 'items.product', 'coupon'])));
    }
}
