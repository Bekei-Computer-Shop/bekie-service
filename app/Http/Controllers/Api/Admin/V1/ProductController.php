<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\Product\IndexProductsRequest;
use App\Http\Requests\Api\Admin\V1\Product\StoreProductRequest;
use App\Http\Requests\Api\Admin\V1\Product\UpdateProductRequest;
use App\Http\Resources\Api\Admin\V1\ProductResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * V1 admin CRUD for the product catalog. Variants are accepted as a
 * nested array on create/update (the user-management plan's
 * "Nested inside Product" choice). Variant identity is keyed by SKU; the
 * set on the product is replaced by the submitted set in a single
 * transaction (the `replace_variants: false` opt-in preserves ids).
 *
 * `Product::getRouteKeyName()` returns `uuid`, so route-model binding
 * resolves via the `uuid` column.
 */
class ProductController extends BaseAdminController
{
    public function index(IndexProductsRequest $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 18);
        $page = (int) $request->input('page', 1);
        $sort = $request->input('sort', 'id');
        $direction = $request->input('direction', 'desc');
        $withTrashed = (bool) $request->input('with_trashed', false);
        $onlyTrashed = (bool) $request->input('only_trashed', false);

        $query = Product::query()->with(['category:id,name,slug', 'brand:id,name,slug']);

        if ($onlyTrashed) {
            $query->onlyTrashed();
        } elseif ($withTrashed) {
            $query->withTrashed();
        }

        if ($search = $request->input('q')) {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', (int) $request->input('brand_id'));
        }

        foreach (['is_active', 'is_featured'] as $flag) {
            if ($request->has($flag)) {
                $query->where($flag, filter_var($request->input($flag), FILTER_VALIDATE_BOOLEAN));
            }
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock_quantity', '<=', 'min_stock_alert');
        }

        $total = (clone $query)->count();
        $items = $query->orderBy($sort, $direction)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->success([
            'items' => ProductResource::collection($items)->resolve($request),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'count' => $items->count(),
            ],
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['category:id,name,slug', 'brand:id,name,slug', 'variants']);

        return $this->success(new ProductResource($product));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $variants = $data['variants'] ?? [];
        unset($data['variants']);

        $data['slug'] = $data['slug'] ?? Str::slug((string) $data['name']);

        $product = DB::transaction(function () use ($data, $variants): Product {
            $product = Product::create($data);

            foreach ($variants as $row) {
                $this->createVariant($product, $row);
            }

            return $product;
        });

        $product->load(['category:id,name,slug', 'brand:id,name,slug', 'variants']);

        return $this->created(new ProductResource($product));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        $variants = $data['variants'] ?? null;
        $replaceVariants = $variants !== null && ! ($request->boolean('replace_variants') === false && $request->has('replace_variants'));
        // Default: replace. Body must explicitly set `replace_variants: false` to opt out.
        if (! $request->has('replace_variants')) {
            $replaceVariants = $variants !== null;
        }

        unset($data['variants'], $data['replace_variants']);

        DB::transaction(function () use ($product, $data, $variants, $replaceVariants): void {
            if ($data !== []) {
                // Cast booleans explicitly so the Eloquent model sees real bools
                // (the FormRequest already coerced, but defence in depth).
                foreach (['is_active', 'is_featured', 'is_digital', 'track_inventory', 'in_stock'] as $flag) {
                    if (array_key_exists($flag, $data) && $data[$flag] !== null) {
                        $data[$flag] = (bool) $data[$flag];
                    }
                }

                $product->fill($data);
                $product->save();
            }

            if ($variants !== null) {
                if ($replaceVariants) {
                    $product->variants()->delete();
                    foreach ($variants as $row) {
                        $this->createVariant($product, $row);
                    }
                } else {
                    // Additive sync by SKU: insert missing, update existing.
                    $existing = $product->variants()->get()->keyBy('sku');
                    foreach ($variants as $row) {
                        $sku = (string) $row['sku'];
                        if ($existing->has($sku)) {
                            $existing->get($sku)->update($this->variantFill($row));
                        } else {
                            $this->createVariant($product, $row);
                        }
                    }
                }
            }
        });

        $product->load(['category:id,name,slug', 'brand:id,name,slug', 'variants']);

        return $this->success(new ProductResource($product));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return $this->noContent();
    }

    public function restore(string $uuid): JsonResponse
    {
        /** @var Product|null $product */
        $product = Product::onlyTrashed()->where('uuid', $uuid)->firstOrFail();
        $product->restore();
        $product->load(['category:id,name,slug', 'brand:id,name,slug', 'variants']);

        return $this->success(new ProductResource($product));
    }

    public function changeStatus(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $request->validate(['is_active' => ['required', 'boolean']]);

        $product->is_active = (bool) $request->input('is_active');
        $product->save();

        return $this->success(new ProductResource($product));
    }

    /**
     * Insert a single variant row, normalising slug + attributes and
     * keeping in_stock in sync with stock_quantity.
     */
    private function createVariant(Product $product, array $row): ProductVariant
    {
        $row['slug'] = ! empty($row['slug']) ? Str::slug((string) $row['slug']) : Str::slug((string) $row['name']);
        $row['product_id'] = $product->id;

        // Normalise `attributes` to an associative array of strings.
        if (isset($row['attributes']) && is_array($row['attributes'])) {
            $normalised = [];
            foreach ($row['attributes'] as $axis => $value) {
                $normalised[(string) $axis] = is_scalar($value) ? (string) $value : json_encode($value);
            }
            $row['attributes'] = $normalised;
        } else {
            $row['attributes'] = [];
        }

        // Auto in_stock flag.
        $stock = (int) ($row['stock_quantity'] ?? 0);
        $row['in_stock'] = $stock > 0;

        return $product->variants()->create($row);
    }

    private function variantFill(array $row): array
    {
        $payload = [
            'name' => $row['name'] ?? null,
            'slug' => ! empty($row['slug']) ? Str::slug((string) $row['slug']) : null,
            'sku' => $row['sku'] ?? null,
            'barcode' => $row['barcode'] ?? null,
            'price' => $row['price'] ?? null,
            'sale_price' => $row['sale_price'] ?? null,
            'cost_price' => $row['cost_price'] ?? null,
            'stock_quantity' => $row['stock_quantity'] ?? null,
            'min_stock_alert' => $row['min_stock_alert'] ?? null,
            'track_inventory' => array_key_exists('track_inventory', $row) ? (bool) $row['track_inventory'] : null,
            'in_stock' => array_key_exists('in_stock', $row) ? (bool) $row['in_stock'] : null,
            'weight' => $row['weight'] ?? null,
            'length' => $row['length'] ?? null,
            'width' => $row['width'] ?? null,
            'height' => $row['height'] ?? null,
            'image' => $row['image'] ?? null,
            'is_default' => array_key_exists('is_default', $row) ? (bool) $row['is_default'] : null,
            'is_active' => array_key_exists('is_active', $row) ? (bool) $row['is_active'] : null,
            'sort_order' => $row['sort_order'] ?? null,
        ];

        if (array_key_exists('attributes', $row) && is_array($row['attributes'])) {
            $normalised = [];
            foreach ($row['attributes'] as $axis => $value) {
                $normalised[(string) $axis] = is_scalar($value) ? (string) $value : json_encode($value);
            }
            $payload['attributes'] = $normalised;
        }

        return array_filter($payload, fn ($v) => $v !== null);
    }
}
