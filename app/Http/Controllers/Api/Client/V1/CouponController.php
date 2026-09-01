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

        if (! $coupon || ! $coupon->isValid()) {
            return $this->error('Coupon code is invalid or expired.', 404);
        }

        $cart = Cart::find($request->cart_id);

        if (! $cart) {
            return $this->error('Cart not found.', 404);
        }

        if ((int) $cart->user_id !== (int) $request->user()?->id) {
            abort(403, 'You do not have access to this cart.');
        }

        $amount = $coupon->calculateDiscount($cart->subtotal);

        // Persist the coupon on the cart so checkout (OrderController@store) can
        // stamp it onto the order and record the usage. Without this the apply
        // call was only ever a preview.
        $cart->update([
            'coupon_code' => $coupon->code,
            'discount_total' => $amount,
        ]);

        return $this->success([
            'coupon' => $coupon->code,
            'discount_amount' => $amount,
            'grand_total' => max(0, $cart->subtotal + $cart->shipping_total + $cart->tax_total - $amount),
            'cart' => new CartResource($cart->load('items.product', 'items.variant')),
        ]);
    }
}
