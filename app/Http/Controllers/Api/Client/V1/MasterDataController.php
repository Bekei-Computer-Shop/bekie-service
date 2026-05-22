<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ShippingMethod;
use App\Http\Resources\Api\Client\V1\BrandResource;
use App\Http\Resources\Api\Client\V1\CategoryResource;
use App\Http\Resources\Api\Client\V1\ShippingMethodResource;
use App\Traits\PaginatesApiRequests;
use Illuminate\Http\JsonResponse;

class MasterDataController extends BaseApiController
{
    use PaginatesApiRequests;

    public function categories(): JsonResponse
    {
        $query = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order');

        $data = $this->paginate($query);

        return $this->success([
            'data' => CategoryResource::collection($data['items']),
            'pagination' => $data['pagination'],
        ], 'Master categories retrieved successfully.');
    }

    public function brands(): JsonResponse
    {
        $query = Brand::where('is_active', true)->orderBy('name');

        $data = $this->paginate($query);

        return $this->success([
            'data' => BrandResource::collection($data['items']),
            'pagination' => $data['pagination'],
        ], 'Master brands retrieved successfully.');
    }

    public function shippingMethods(): JsonResponse
    {
        $query = ShippingMethod::where('is_active', true)->orderBy('sort_order');

        $data = $this->paginate($query);

        return $this->success([
            'data' => ShippingMethodResource::collection($data['items']),
            'pagination' => $data['pagination'],
        ], 'Master shipping methods retrieved successfully.');
    }
}
