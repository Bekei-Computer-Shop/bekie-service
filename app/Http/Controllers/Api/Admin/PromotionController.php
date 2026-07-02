<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Requests\Admin\StorePromotionRequest;
use App\Http\Requests\Admin\UpdatePromotionRequest;
use App\Http\Resources\Admin\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;

class PromotionController extends BaseAdminController
{
    public function index(): JsonResponse
    {
        $promotions = Promotion::latest()->paginate(15);

        return $this->success(PromotionResource::collection($promotions));
    }

    public function show(Promotion $promotion): JsonResponse
    {
        return $this->success(new PromotionResource($promotion));
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $promotion = Promotion::create($request->validated());

        return $this->created(new PromotionResource($promotion));
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): JsonResponse
    {
        $promotion->update($request->validated());

        return $this->success(new PromotionResource($promotion));
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $promotion->delete();

        return $this->noContent();
    }
}
