<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\V1\StoreOrderRequest;
use App\Http\Resources\V1\OrderResource;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = Order::with('items');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        return $this->success(OrderResource::collection($query->orderBy('created_at', 'desc')->paginate(15)));
    }

    public function store(StoreOrderRequest $request)
    {
        $cart = Cart::with('items.product', 'items.variant')->findOrFail($request->cart_id);

        if ($cart->items->isEmpty()) {
            return $this->error('Cart must contain at least one item.', 422);
        }

        $shippingMethod = ShippingMethod::findOrFail($request->shipping_method_id);
        $addressSnapshot = $this->resolveAddressSnapshot($request);

        $shippingWeight = $this->calculateCartWeight($cart);

        $order = Order::create([
            'user_id' => $cart->user_id,
            'address_id' => $request->address_id,
            'order_number' => $this->generateOrderNumber(),
            'subtotal' => $cart->subtotal,
            'discount_total' => $cart->discount_total,
            'tax_total' => $cart->tax_total,
            'shipping_total' => $shippingMethod->calculateCost($shippingWeight),
            'grand_total' => $cart->subtotal + $cart->tax_total + $shippingMethod->calculateCost($shippingWeight) - $cart->discount_total,
            'currency' => $cart->currency,
            'payment_method' => $request->input('payment_method', 'manual'),
            'payment_status' => 'pending',
            'transaction_id' => null,
            'status' => 'pending',
            'shipping_status' => 'pending',
            'tracking_number' => null,
            'shipping_provider' => $shippingMethod->name,
            'customer_snapshot' => [
                'user_id' => $cart->user_id,
                'email' => $request->email,
                'phone' => $request->phone,
            ],
            'address_snapshot' => $addressSnapshot,
            'metadata' => $request->input('metadata', []),
        ]);

        foreach ($cart->items as $cartItem) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $cartItem->product_id,
                'product_variant_id' => $cartItem->product_variant_id,
                'quantity' => $cartItem->quantity,
                'unit_price' => $cartItem->unit_price,
                'sale_price' => $cartItem->sale_price,
                'cost_price' => $cartItem->cost_price,
                'subtotal' => $cartItem->subtotal,
                'discount' => $cartItem->discount,
                'tax' => 0,
                'total' => $cartItem->total,
                'product_name' => $cartItem->product_name,
                'product_sku' => $cartItem->product_sku,
                'variant_name' => $cartItem->variant_name,
                'variant_attributes' => $cartItem->variant_attributes,
                'quantity_shipped' => 0,
                'quantity_refunded' => 0,
                'status' => 'pending',
                'metadata' => $cartItem->metadata,
            ]);

            if ($cartItem->product?->track_inventory) {
                $cartItem->product->decrement('stock_quantity', $cartItem->quantity);
            }

            if ($cartItem->variant?->track_inventory) {
                $cartItem->variant->decrement('stock_quantity', $cartItem->quantity);
            }
        }

        $cart->update(['status' => 'converted']);

        return $this->created(new OrderResource($order->load('items')));
    }

    public function show(Order $order)
    {
        return $this->success(new OrderResource($order->load('items')));
    }

    protected function generateOrderNumber(): string
    {
        return 'ORD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5));
    }

    protected function resolveAddressSnapshot(StoreOrderRequest $request): array
    {
        if ($request->filled('address_id')) {
            $address = Address::find($request->address_id);

            return $address ? $address->only([
                'full_name',
                'phone',
                'email',
                'company',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'postal_code',
                'country',
            ]) : [];
        }

        return [
            'recipient_name' => $request->recipient_name,
            'phone' => $request->phone,
            'address_line_1' => $request->address_line_1,
            'address_line_2' => $request->address_line_2,
            'city' => $request->city,
            'state' => $request->state,
            'postal_code' => $request->postal_code,
            'country' => $request->country,
        ];
    }

    protected function calculateCartWeight(Cart $cart): float
    {
        return $cart->items->sum(function ($item) {
            return $item->quantity * ($item->variant?->weight ?? $item->product?->weight ?? 0);
        });
    }
}
