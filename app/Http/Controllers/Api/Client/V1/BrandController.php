<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Resources\Api\Client\V1\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends BaseApiController
{
    public function index(Request $request)
    {
        $brands = Brand::where('is_active', true)
            ->orderBy('sort_order')
            ->paginate(16);

        return $this->success(BrandResource::collection($brands));
    }

    public function show(Brand $brand)
    {
        return $this->success(new BrandResource($brand));
    }
}
