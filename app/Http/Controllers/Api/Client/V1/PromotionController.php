<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Resources\Api\Client\V1\PromotionResource;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Storefront promotions.
 *
 * Admin "Promotions" are backed by the `Coupon` model, so this public list is
 * also served from `coupons`. Only promotions a shopper could actually use
 * right now are returned: active, within their validity window and with usage
 * capacity remaining.
 */
class PromotionController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $promotions = Coupon::query()
            ->active()
            ->valid()
            ->where(function (Builder $query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();

        return $this->success(PromotionResource::collection($promotions));
    }
}