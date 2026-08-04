<?php

namespace App\Services\Admin;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function listProducts(array $filters)
    {
        return Product::query()
            ->with(['brand', 'categories', 'variants.inventory'])
            ->when($filters['category_id'] ?? null, fn($q, $id) => $q->whereHas('categories', fn($sq) => $sq->where('id', $id)))
            ->when($filters['brand_id'] ?? null, fn($q, $id) => $q->where('brand_id', $id))
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->paginate($filters['per_page'] ?? 15);
    }

    public function createProduct(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create($data);

            if (isset($data['category_ids'])) {
                $product->categories()->sync($data['category_ids']);
            }

            foreach ($data['variants'] as $variantData) {
                $variant = $product->variants()->create($variantData);
                $variant->inventory()->create(['quantity' => $variantData['stock']]);
            }

            if (isset($data['images'])) {
                foreach ($data['images'] as $image) {
                    $path = $image->store('products', 'public');
                    $product->images()->create(['path' => $path]);
                }
            }

            return $product->load(['variants.inventory', 'images', 'categories']);
        });
    }

    public function updateProduct(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->update($data);

            if (isset($data['category_ids'])) {
                $product->categories()->sync($data['category_ids']);
            }

            // Simplified variant sync: this would normally involve identifying
            // existing, new, and deleted variants.
            if (isset($data['variants'])) {
                foreach ($data['variants'] as $variantData) {
                    $variant = $product->variants()->updateOrCreate(
                        ['sku' => $variantData['sku']],
                        $variantData
                    );
                    $variant->inventory()->updateOrCreate([], ['quantity' => $variantData['stock']]);
                }
            }

            return $product->load(['variants.inventory', 'images', 'categories']);
        });
    }

    public function deleteProduct(Product $product): void
    {
        DB::transaction(function () use ($product) {
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->path);
            }
            $product->delete();
        });
    }

    public function updateStatus(Product $product, string $status): void
    {
        $product->update(['status' => $status]);
    }
}
