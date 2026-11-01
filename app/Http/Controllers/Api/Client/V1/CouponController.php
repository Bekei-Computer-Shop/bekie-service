<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\ApplyCouponRequest;
use App\Http\Resources\Api\Client\V1\CartResource;
use App\Models\Cart;
use App\Models\Coupon;
use App\Services\ProductPromotionService;

class CouponController extends BaseApiController
{
    public function apply(ApplyCouponRequest $request)
    {
        $coupon = Coupon::active()
            ->valid()
            ->where('code', $request->code)
            ->first();

        if (! $coupon || ! $coupon->isValid()) {
            $message = "This coupon code isn't valid or has expired. Please check the code and try again.";

            return $this->error($message, 404, ['code' => [$message]], 'coupon_invalid');
        }

        $cart = Cart::whereKey($request->cart_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $cart) {
            return $this->error("We couldn't find your cart. Please refresh the page and try again.", 404, code: 'cart_not_found');
        }

        if ((int) $cart->user_id !== (int) $request->user()?->id) {
            abort(403, 'This cart belongs to another account.');
        }

        $amount = app(ProductPromotionService::class)->couponCodeDiscount(
            $coupon,
            (int) $request->user()->id,
            (float) $cart->subtotal,
        );

        if ($amount <= 0) {
            $message = "This coupon can't be used with your account or the items in your cart.";

            return $this->error($message, 422, ['code' => [$message]], 'coupon_not_eligible');
        }

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
