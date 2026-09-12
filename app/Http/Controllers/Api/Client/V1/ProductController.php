<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\ListProductsRequest;
use App\Http\Resources\Api\Client\V1\ProductResource;
use App\Http\Resources\Api\Client\V1\ProductVariantResource;
use App\Models\Product;

class ProductController extends BaseApiController
{
    public function index(ListProductsRequest $request)
    {
        $query = Product::where('is_active', true);

        // Filter by category (supports single or multiple)
        if ($request->filled('category_id')) {
            $categoryIds = is_array($request->query('category_id'))
                ? $request->query('category_id')
                : [$request->query('category_id')];
            $query->whereIn('category_id', $categoryIds);
        }

        // Filter by brand (supports single or multiple)
        if ($request->filled('brand_id')) {
            $brandIds = is_array($request->query('brand_id'))
                ? $request->query('brand_id')
                : [$request->query('brand_id')];
            $query->whereIn('brand_id', $brandIds);
        }

        // Filter by price range
        if ($request->filled('min_price')) {
            $minPrice = (float) $request->query('min_price');
            $query->where(function ($q) use ($minPrice) {
                $q->where('sale_price', '>=', $minPrice)
                    ->orWhere(function ($q2) use ($minPrice) {
                        $q2->whereNull('sale_price')
                            ->where('price', '>=', $minPrice);
                    });
            });
        }

        if ($request->filled('max_price')) {
            $maxPrice = (float) $request->query('max_price');
            $query->where(function ($q) use ($maxPrice) {
                $q->where('sale_price', '<=', $maxPrice)
                    ->orWhere(function ($q2) use ($maxPrice) {
                        $q2->whereNull('sale_price')
                            ->where('price', '<=', $maxPrice);
                    });
            });
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        // Sort options
        $sortBy = $request->query('sort_by', 'featured'); // featured, price_asc, price_desc, newest, popular

        match ($sortBy) {
            'price_asc' => $query->orderBy('sale_price')->orderBy('price'),
            'price_desc' => $query->orderByDesc('sale_price')->orderByDesc('price'),
            'newest' => $query->orderByDesc('created_at'),
            'popular' => $query->orderByDesc('sales_count'),
            default => $query->orderBy('is_featured', 'desc')->orderBy('sort_order'),
        };

        $products = $query->paginate(
            perPage: (int) $request->query('per_page', 18),
            page: (int) $request->query('page', 1)
        );

        return $this->success(ProductResource::collection($products));
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        $product->load([
            'images' => fn ($query) => $query->where('is_active', true),
            'variants' => fn ($query) => $query->where('is_active', true),
        ]);

        $product->increment('views_count');

        return $this->success(new ProductResource($product));
    }

    public function variants(Product $product)
    {
        return $this->success(ProductVariantResource::collection($product->variants()->where('is_active', true)->orderBy('sort_order')->get()));
    }
}
