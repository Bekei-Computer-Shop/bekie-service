<?php

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\StoreProductRequest;
use App\Http\Resources\Api\Client\V1\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @group Admin APIs
 *
 * @subgroup Product Management
 * @authenticated
 */
class ProductController extends BaseAdminController
{
    /**
     * Create Product with Variants
     *
     * Creates a new product and its associated variants in a single transaction.
     * The `variants` array is optional. If not provided, a product without variants will be created.
     *
     * @responseFile 201 storage/responses/client/product.json
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $product = DB::transaction(function () use ($validated) {
            $product = Product::create($validated['product']);

            if (! empty($validated['variants'])) {
                $product->variants()->createMany($validated['variants']);
            }

            return $product;
        });

        return $this->created(
            data: ProductResource::make($product->load(['category', 'brand', 'variants'])),
            message: 'Product created successfully.'
        );
    }
}
