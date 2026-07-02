<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Requests\Admin\StoreOrderRequest;
use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Str;

class OrderController extends BaseAdminController
{
    public function index(Request $request)
    {
        $orders = Order::with(['user', 'items.product'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('date_to')))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('user_id', $request->input('customer_id')))
            ->latest()
            ->paginate(15);

        return $this->success(OrderResource::collection($orders));
    }

    public function show(Order $order)
    {
        $order->load(['user', 'items.product']);

        return $this->success(new OrderResource($order));
    }

    public function store(StoreOrderRequest $request)
    {
        $validated = $request->validated();

        $order = Order::create([
            'order_number' => Str::upper(Str::random(10)),
            'user_id' => $validated['customer_id'],
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
            'currency' => 'USD',
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 0,
        ]);

        $items = collect($validated['items'])->map(function (array $item) use ($order) {
            $product = Product::findOrFail($item['product_id']);
            $lineTotal = $item['qty'] * $item['unit_price'];

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

        $grandTotal = $items->sum(fn ($item) => $item->total);

        $order->update(['subtotal' => $grandTotal, 'grand_total' => $grandTotal]);

        return $this->created(new OrderResource($order->fresh(['user', 'items.product'])));
    }

    public function update(UpdateOrderRequest $request, Order $order)
    {
        $validated = $request->validated();

        $order->update($validated);

        return $this->success(new OrderResource($order->fresh(['user', 'items.product'])));
    }

    public function destroy(Order $order)
    {
        $order->delete();

        return $this->noContent();
    }

    public function approve(Order $order)
    {
        $order->update(['status' => 'approved']);

        return $this->success(new OrderResource($order->fresh(['user', 'items.product'])));
    }

    public function reject(Order $order)
    {
        $order->update(['status' => 'rejected']);

        return $this->success(new OrderResource($order->fresh(['user', 'items.product'])));
    }
}
