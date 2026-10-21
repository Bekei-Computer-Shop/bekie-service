<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Resources\Api\Client\V1\ShippingMethodResource;
use App\Models\ShippingMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShippingMethodController extends BaseAdminController
{
    public function index(): JsonResponse
    {
        $methods = ShippingMethod::query()->ordered()->get();

        return $this->success(ShippingMethodResource::collection($methods)->resolve());
    }

    public function store(Request $request): JsonResponse
    {
        $method = ShippingMethod::query()->create($request->validate($this->rules()));

        return $this->created(
            (new ShippingMethodResource($method))->resolve(),
            'Shipping method created successfully.',
        );
    }

    public function update(Request $request, ShippingMethod $shippingMethod): JsonResponse
    {
        $shippingMethod->update($request->validate($this->rules($shippingMethod)));

        return $this->success(
            (new ShippingMethodResource($shippingMethod->refresh()))->resolve(),
            'Shipping method updated successfully.',
        );
    }

    public function destroy(ShippingMethod $shippingMethod): JsonResponse
    {
        $shippingMethod->delete();

        return $this->noContent();
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    private function rules(?ShippingMethod $shippingMethod = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:100', 'alpha_dash',
                Rule::unique('shipping_methods', 'code')->ignore($shippingMethod?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'price_per_kg' => ['nullable', 'numeric', 'min:0'],
            'min_weight' => ['nullable', 'numeric', 'min:0'],
            'max_weight' => ['nullable', 'numeric', 'min:0', 'gte:min_weight'],
            'min_delivery_days' => ['nullable', 'integer', 'min:0'],
            'max_delivery_days' => ['nullable', 'integer', 'min:0', 'gte:min_delivery_days'],
            'is_active' => ['required', 'boolean'],
            'type' => ['required', 'string', 'max:80'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
