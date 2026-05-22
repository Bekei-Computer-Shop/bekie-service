<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\ApplyCouponRequest;
use App\Http\Resources\Api\Client\V1\CartResource;
use App\Models\Cart;
use App\Models\Coupon;

class CouponController extends BaseApiController
{
    public function apply(ApplyCouponRequest $request)
    {
        $coupon = Coupon::active()
            ->valid()
            ->where('code', $request->code)
            ->first();

        if (! $coupon) {
            return $this->error('Coupon code is invalid or expired.', 404);
        }

        $cart = Cart::find($request->cart_id);

        if (! $cart) {
            return $this->error('Cart not found.', 404);
        }

        $amount = $coupon->calculateDiscount($cart->subtotal);

        return $this->success([
            'coupon' => $coupon->code,
            'discount_amount' => $amount,
            'grand_total' => max(0, $cart->subtotal + $cart->shipping_total + $cart->tax_total - $amount),
            'cart' => new CartResource($cart->load('items.product', 'items.variant')),
        ]);
    }
}
