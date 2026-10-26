<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Resources\Api\Client\V1\BrandResource;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BrandController extends BaseApiController
{
    public function index(Request $request)
    {
        $brands = Brand::withCount([
            'products as active_products_count' => static fn (Builder $query) => $query->where('is_active', true),
        ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->paginate(16);

        return $this->success(BrandResource::collection($brands));
    }

    public function show(Brand $brand)
    {
        $brand->loadCount([
            'products as active_products_count' => static fn (Builder $query) => $query->where('is_active', true),
        ]);

        return $this->success(new BrandResource($brand));
    }
}
