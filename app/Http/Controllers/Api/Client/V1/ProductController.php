<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Resources\Api\Client\V1\ProductResource;
use App\Http\Resources\Api\Client\V1\ProductVariantResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand'])
            ->where('is_active', true);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->query('brand_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        $products = $query
            ->orderBy('is_featured', 'desc')
            ->orderBy('sort_order')
            ->paginate(18);

        return $this->success(ProductResource::collection($products));
    }

    public function show(Product $product)
    {
        // Unpublished products are not part of the storefront catalog.
        abort_unless($product->is_active, 404);

        $product->load([
            'category',
            'brand',
            // The storefront gallery is assembled from the active images, in
            // the display order (`sort_order`, then id) the admin configured.
            'images' => fn ($query) => $query->where('is_active', true),
            // Only variants shoppers can actually buy.
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
