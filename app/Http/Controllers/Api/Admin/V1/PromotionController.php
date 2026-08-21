<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\StorePromotionRequest;
use App\Http\Requests\Api\Admin\V1\UpdatePromotionRequest;
use App\Http\Resources\Api\Admin\V1\PromotionResource;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;

class PromotionController extends BaseAdminController
{
    public function index(): JsonResponse
    {
        $promotions = Coupon::query()->latest()->paginate(15);

        return $this->success(PromotionResource::collection($promotions));
    }

    public function show(Coupon $promotion): JsonResponse
    {
        return $this->success(new PromotionResource($promotion));
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $promotion = Coupon::create($request->validated());

        return $this->created(new PromotionResource($promotion));
    }

    public function update(UpdatePromotionRequest $request, Coupon $promotion): JsonResponse
    {
        $promotion->update($request->validated());

        return $this->success(new PromotionResource($promotion));
    }

    public function destroy(Coupon $promotion): JsonResponse
    {
        $promotion->delete();

        return $this->noContent();
    }
}
