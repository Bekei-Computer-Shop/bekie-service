<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\Admin\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Str;

class ProductController extends BaseAdminController
{
    public function index(Request $request)
    {
        $products = Product::with('category')
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->input('category_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->input('status') === 'active'))
            ->when($request->boolean('low_stock'), fn ($query) => $query->lowStock())
            ->latest()
            ->paginate(15);

        return $this->success(ProductResource::collection($products));
    }

    public function show(Product $product)
    {
        $product->load('category');

        return $this->success(new ProductResource($product));
    }

    public function store(StoreProductRequest $request)
    {
        $validated = $request->validated();

        $product = Product::create([
            'uuid' => $validated['uuid'] ?? null,
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'price' => $validated['price'],
            'stock_quantity' => $validated['stock_qty'],
            'min_stock_alert' => $validated['low_stock_threshold'],
            'is_active' => $validated['status'] === 'active',
            'slug' => Str::slug($validated['name']),
        ]);

        return $this->created(new ProductResource($product->load('category')));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $validated = $request->validated();

        $product->update(array_filter([
            'category_id' => $validated['category_id'] ?? $product->category_id,
            'name' => $validated['name'] ?? $product->name,
            'sku' => $validated['sku'] ?? $product->sku,
            'price' => $validated['price'] ?? $product->price,
            'stock_quantity' => $validated['stock_qty'] ?? $product->stock_quantity,
            'min_stock_alert' => $validated['low_stock_threshold'] ?? $product->min_stock_alert,
            'is_active' => isset($validated['status']) ? $validated['status'] === 'active' : $product->is_active,
            'slug' => isset($validated['name']) ? Str::slug($validated['name']) : $product->slug,
        ]));

        return $this->success(new ProductResource($product->fresh('category')));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return $this->noContent();
    }
}
