<?php

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V1\StoreProductRequest;
use App\Http\Resources\Admin\V1\ProductResource;
use App\Models\Product;
use App\Services\Admin\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(protected ProductService $productService)
    {
        $this->authorizeResource(Product::class, 'product');
    }

    public function index(Request $request)
    {
        $products = $this->productService->listProducts($request->all());
        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request)
    {
        $product = $this->productService->createProduct($request->validated());
        return new ProductResource($product);
    }

    public function show(Product $product)
    {
        return new ProductResource($product->load(['brand', 'categories', 'variants.inventory', 'images']));
    }

    public function update(StoreProductRequest $request, Product $product)
    {
        $updated = $this->productService->updateProduct($product, $request->validated());
        return new ProductResource($updated);
    }

    public function destroy(Product $product)
    {
        $this->productService->deleteProduct($product);
        return response()->noContent();
    }

    public function changeStatus(Request $request, Product $product)
    {
        $request->validate(['status' => 'required|in:draft,published,out_of_stock']);
        $this->productService->updateStatus($product, $request->status);
        return response()->json(['message' => 'Status updated successfully']);
    }

    public function bulkUpdateStatus(Request $request)
    {
        // Implementation for bulk status change
    }

    public function bulkDestroy(Request $request)
    {
        // Implementation for bulk deletion
    }
}
