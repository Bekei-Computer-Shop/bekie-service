<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\AddCartItemRequest;
use App\Http\Requests\Api\Client\V1\StoreOrderRequest;
use App\Http\Requests\Api\Client\V1\UpdateCartItemRequest;
use App\Http\Resources\Api\Client\V1\CartResource;
use App\Http\Resources\Api\Client\V1\OrderResource;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Shopping Cart Management
 *
 * Manage customer shopping carts and checkout process.
 * Authenticated users have a single cart; guest carts are identified by session_id.
 */
class CartController extends BaseApiController
{
    /**
     * Get authenticated user's cart
     *
     * Returns the authenticated user's single cart with all items.
     * Automatically creates the cart if it doesn't exist.
     *
     * @response 200 {
     *   "status": "success",
     *   "data": {
     *     "id": 1,
     *     "user_id": 5,
     *     "currency": "USD",
     *     "subtotal": 99.99,
     *     "discount_total": 0,
     *     "tax_total": 8.00,
     *     "shipping_total": 10.00,
     *     "grand_total": 117.99,
     *     "status": "active",
     *     "items": [...]
     *   }
     * }
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $cart = Cart::firstOrCreate(
            ['user_id' => $user->id, 'session_id' => null],
            [
                'currency' => 'USD',
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'shipping_total' => 0,
                'grand_total' => 0,
                'status' => 'active',
                'last_activity_at' => now(),
            ]
        );

        return $this->success(new CartResource($cart->load('items.product', 'items.variant')));
    }

    /**
     * Update authenticated user's cart
     *
     * Updates the cart settings for the authenticated user.
     * Creates the cart if it doesn't exist.
     *
     * @bodyParam currency string Currency code (max 3 chars). Example: "USD"
     * @bodyParam status string Cart status (active, abandoned, converted). Example: "active"
     *
     * @response 200 {
     *   "status": "success",
     *   "data": {
     *     "id": 1,
     *     "user_id": 5,
     *     "currency": "USD",
     *     "subtotal": 0,
     *     "grand_total": 0,
     *     "items": []
     *   }
     * }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'currency' => 'sometimes|string|max:3',
            'status' => 'sometimes|string|in:active,abandoned,converted',
        ]);

        $user = $request->user();

        $cart = Cart::firstOrCreate(
            ['user_id' => $user->id, 'session_id' => null],
            [
                'currency' => 'USD',
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'shipping_total' => 0,
                'grand_total' => 0,
                'status' => 'active',
                'last_activity_at' => now(),
            ]
        );

        if (! empty($validated)) {
            $cart->update($validated);
        }

        return $this->success(new CartResource($cart->fresh()->load('items.product', 'items.variant')));
    }

    /**
     * Add product to authenticated user's cart
     *
     * Adds a product (with optional variant) to the user's cart.
     * If the product already exists in cart, quantity is updated.
     * Cart totals are automatically recalculated.
     *
     * @bodyParam product_id string required The product UUID. Example: "550e8400-e29b-41d4-a716-446655440000"
     * @bodyParam product_variant_id integer The product variant ID (optional). Example: 1
     * @bodyParam quantity integer required The quantity to add (min: 1). Example: 2
     *
     * @response 201 {
     *   "status": "success",
     *   "data": {
     *     "id": 1,
     *     "user_id": 5,
     *     "subtotal": 199.98,
     *     "grand_total": 207.98,
     *     "items": [...]
     *   }
     * }
     * @response 422 {
     *   "status": "error",
     *   "message": "The quantity must be at least 1.",
     *   "errors": {"quantity": ["The quantity must be at least 1."]}
     * }
     */
    public function addItem(AddCartItemRequest $request)
    {
        $cart = Cart::firstOrCreate(
            ['user_id' => $request->user()->id, 'session_id' => null],
            [
                'currency' => 'USD',
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'shipping_total' => 0,
                'grand_total' => 0,
                'status' => 'active',
                'last_activity_at' => now(),
            ]
        );

        $product = Product::find($request->product_id);
        $variant = null;

        if ($request->filled('product_variant_id')) {
            $variant = ProductVariant::find($request->product_variant_id);
        }

        $quantity = $request->quantity;
        $unitPrice = $variant?->price ?? $product->sale_price ?? $product->price;
        $salePrice = $variant?->sale_price ?? $product->sale_price;
        $costPrice = $variant?->cost_price ?? $product->cost_price;

        $item = $cart->items()->updateOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
            ],
            [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'sale_price' => $salePrice,
                'cost_price' => $costPrice,
                'subtotal' => $unitPrice * $quantity,
                'discount' => 0,
                'total' => $unitPrice * $quantity,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'variant_name' => $variant?->name,
                'variant_attributes' => $variant?->attributes,
                'is_available' => true,
            ]
        );

        $this->recalculateCart($cart);

        return $this->created(new CartResource($cart->fresh()->load('items.product', 'items.variant')));
    }

    /**
     * Update cart item quantity
     *
     * Updates the quantity of an item in the authenticated user's cart.
     * Cart totals are automatically recalculated.
     *
     * @bodyParam quantity integer required The new quantity (min: 1). Example: 3
     *
     * @response 200 {
     *   "status": "success",
     *   "data": {
     *     "id": 1,
     *     "subtotal": 299.97,
     *     "grand_total": 307.97,
     *     "items": [...]
     *   }
     * }
     * @response 422 {
     *   "status": "error",
     *   "message": "The quantity must be at least 1.",
     *   "errors": {"quantity": ["The quantity must be at least 1."]}
     * }
     * @response 404 Item not found
     */
    public function updateItem(UpdateCartItemRequest $request, CartItem $item)
    {
        $cart = Cart::where('user_id', $request->user()->id)->firstOrFail();
        $this->ensureItemBelongsToCart($cart, $item);

        $item->update([
            'quantity' => $request->quantity,
            'subtotal' => $item->unit_price * $request->quantity,
            'total' => $item->unit_price * $request->quantity,
        ]);

        $this->recalculateCart($cart);

        return $this->success(new CartResource($cart->fresh()->load('items.product', 'items.variant')));
    }

    /**
     * Remove item from authenticated user's cart
     *
     * Removes a product from the user's cart.
     * Cart totals are automatically recalculated.
     *
     * @response 204 Item removed successfully
     * @response 404 Item not found
     */
    public function removeItem(Request $request, CartItem $item)
    {
        $cart = Cart::where('user_id', $request->user()->id)->firstOrFail();
        $this->ensureItemBelongsToCart($cart, $item);

        $item->delete();

        $this->recalculateCart($cart);

        return $this->noContent();
    }

    /**
     * Checkout authenticated user's cart and create order
     *
     * Converts the user's cart into an order. Validates cart contents, applies shipping,
     * deducts inventory, and marks cart as converted.
     * Returns the created order with all details.
     *
     * @bodyParam shipping_method_id integer required The shipping method ID. Example: 1
     * @bodyParam email string required Customer email. Example: "customer@example.com"
     * @bodyParam phone string required Customer phone. Example: "+1234567890"
     * @bodyParam address_id integer User's saved address ID (optional if providing address fields). Example: 1
     * @bodyParam recipient_name string Recipient full name (if not using saved address). Example: "John Doe"
     * @bodyParam address_line_1 string Street address (if not using saved address). Example: "123 Main St"
     * @bodyParam address_line_2 string Additional address info (optional). Example: "Apt 4B"
     * @bodyParam city string City (if not using saved address). Example: "New York"
     * @bodyParam state string State/Province (if not using saved address). Example: "NY"
     * @bodyParam postal_code string Postal code (if not using saved address). Example: "10001"
     * @bodyParam country string Country (if not using saved address). Example: "US"
     * @bodyParam payment_method string Payment method. Default: "manual". Example: "credit_card"
     * @bodyParam metadata object Additional metadata as JSON. Example: {}
     *
     * @response 201 {
     *   "status": "success",
     *   "data": {
     *     "id": 1,
     *     "order_number": "ORD-20240101120000-ABC12",
     *     "user_id": 5,
     *     "subtotal": 99.99,
     *     "tax_total": 8.00,
     *     "shipping_total": 10.00,
     *     "grand_total": 117.99,
     *     "status": "pending",
     *     "payment_status": "pending",
     *     "items": [...]
     *   }
     * }
     * @response 422 {
     *   "status": "error",
     *   "message": "Cart is empty.",
     *   "errors": {}
     * }
     * @response 404 Cart not found
     */
    public function checkout(StoreOrderRequest $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)->firstOrFail();

        if ($cart->items()->count() === 0) {
            return $this->error('Cart is empty.', 422);
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

    protected function recalculateCart(Cart $cart): Cart
    {
        $items = $cart->items;

        $subtotal = $items->sum(fn ($item) => $item->total);

        $cart->update([
            'subtotal' => $subtotal,
            'grand_total' => $subtotal + $cart->tax_total + $cart->shipping_total - $cart->discount_total,
            'last_activity_at' => now(),
        ]);

        return $cart;
    }

    protected function ensureItemBelongsToCart(Cart $cart, CartItem $item): void
    {
        if ((int) $item->cart_id !== (int) $cart->id) {
            abort(404, 'Cart item not found.');
        }
    }

    protected function generateOrderNumber(): string
    {
        return 'ORD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5));
    }

    protected function resolveAddressSnapshot(StoreOrderRequest $request): array
    {
        if ($request->filled('address_id')) {
            $address = Address::query()
                ->whereKey($request->address_id)
                ->where('user_id', $request->user()?->id)
                ->first();

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
