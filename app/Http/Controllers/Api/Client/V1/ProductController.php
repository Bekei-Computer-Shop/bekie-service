<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\ListNewProductsRequest;
use App\Http\Requests\Api\Client\V1\ListProductReviewsRequest;
use App\Http\Requests\Api\Client\V1\ListProductsRequest;
use App\Http\Resources\Api\Client\V1\ContentPageResource;
use App\Http\Resources\Api\Client\V1\ProductImageResource;
use App\Http\Resources\Api\Client\V1\ProductListResource;
use App\Http\Resources\Api\Client\V1\ProductResource;
use App\Http\Resources\Api\Client\V1\ProductReviewResource;
use App\Http\Resources\Api\Client\V1\ProductSpecificationsResource;
use App\Http\Resources\Api\Client\V1\ProductVariantResource;
use App\Http\Resources\Api\Client\V1\RelatedProductResource;
use App\Models\ContentItem;
use App\Models\Product;
use App\Models\Review;
use App\Services\ProductPromotionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends BaseApiController
{
    public function bestSelling(Request $request, ProductPromotionService $pricing): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 8), 1), 24);
        $sales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->select('order_items.product_id')
            ->selectRaw('SUM(CASE WHEN order_items.quantity > order_items.quantity_refunded THEN order_items.quantity - order_items.quantity_refunded ELSE 0 END) as units_sold')
            ->where('orders.payment_status', 'paid')
            ->whereNotIn('orders.status', ['cancelled'])
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at')
            ->groupBy('order_items.product_id');

        $products = Product::query()
            ->joinSub($sales, 'sales', 'sales.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->whereHas('approvedReviews')
            ->select('products.*', 'sales.units_sold as sales_count')
            ->addSelect([
                'average_rating' => DB::table('reviews')
                    ->select(DB::raw('COALESCE(ROUND(AVG(rating), 1), 0)'))
                    ->whereColumn('product_id', 'products.id')
                    ->where('status', 'approved')
                    ->whereNull('deleted_at'),
            ])
            ->withCount(['approvedReviews as reviews_count'])
            ->orderByDesc('sales.units_sold')
            ->orderBy('products.id')
            ->limit($limit)
            ->get();
        $pricing->attachStorefrontPromotions($products);

        return $this->success(ProductListResource::collection($products));
    }

    /**
     * Products with a lower sale price, or targeted by a live promotion through
     * the product, one of its variants or its category. Promotions limited to
     * customer groups are left out: this list is public, not per shopper.
     */
    public function onPromotion(Request $request, ProductPromotionService $pricing): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 8), 1), 24);
        ['product_ids' => $productIds, 'category_ids' => $categoryIds] = $pricing->storefrontTargets();

        $products = Product::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($productIds, $categoryIds): void {
                $query->where(fn (Builder $sale) => $sale->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'price'))
                    ->orWhereIn('id', $productIds)
                    ->orWhereIn('category_id', $categoryIds);
            })
            ->addSelect([
                'average_rating' => DB::table('reviews')
                    ->select(DB::raw('COALESCE(ROUND(AVG(rating), 1), 0)'))
                    ->whereColumn('product_id', 'products.id')
                    ->where('status', 'approved')
                    ->whereNull('deleted_at'),
            ])
            ->withCount(['approvedReviews as reviews_count'])
            ->orderByRaw('CASE WHEN sale_price < price THEN price - sale_price ELSE 0 END DESC')
            ->orderByDesc('is_featured')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $pricing->attachStorefrontPromotions($products);

        return $this->success(ProductListResource::collection($products));
    }

    public function newProducts(ListNewProductsRequest $request, ProductPromotionService $pricing): JsonResponse
    {
        $validated = $request->validated();
        $now = now();
        $products = Product::query()
            ->where('is_active', true)
            ->where('in_stock', true)
            ->where('stock_quantity', '>', 0)
            ->whereBetween('created_at', [$now->copy()->subDays(30), $now])
            ->addSelect([
                'average_rating' => DB::table('reviews')
                    ->select(DB::raw('COALESCE(ROUND(AVG(rating), 1), 0)'))
                    ->whereColumn('product_id', 'products.id')
                    ->where('status', 'approved')
                    ->whereNull('deleted_at'),
            ])
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->paginate(
                perPage: (int) ($validated['limit'] ?? 20),
                page: (int) ($validated['page'] ?? 1)
            );
        $pricing->attachStorefrontPromotions($products);

        return $this->success(ProductListResource::collection($products));
    }

    public function index(ListProductsRequest $request, ProductPromotionService $pricing)
    {
        $query = Product::where('is_active', true)
            ->addSelect([
                'average_rating' => DB::table('reviews')
                    ->select(DB::raw('COALESCE(ROUND(AVG(rating), 1), 0)'))
                    ->whereColumn('product_id', 'products.id')
                    ->where('status', 'approved')
                    ->whereNull('deleted_at'),
            ]);

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
        $pricing->attachStorefrontPromotions($products);

        return $this->success(ProductListResource::collection($products));
    }

    public function show(Product $product, ProductPromotionService $pricing)
    {
        abort_unless($product->is_active, 404);

        $product->load([
            'category',
            'brand',
            'images' => fn ($query) => $query->where('is_active', true),
            'variants' => fn ($query) => $query->where('is_active', true),
            'approvedReviews',
        ]);

        // Calculate average rating from approved reviews
        if ($product->approvedReviews->isNotEmpty()) {
            $product->average_rating = round($product->approvedReviews->avg('rating'), 1);
        } else {
            $product->average_rating = 0;
        }

        $product->increment('views_count');
        $pricing->attachStorefrontPromotions([$product]);

        return $this->success(new ProductResource($product));
    }

    public function variants(Product $product)
    {
        return $this->success(ProductVariantResource::collection($product->variants()->where('is_active', true)->orderBy('sort_order')->get()));
    }

    public function gallery(Product $product): JsonResponse
    {
        $this->assertClientVisible($product);
        $product->load(['images' => fn ($query) => $query->where('is_active', true)]);

        $primaryImage = $product->images->first(fn ($image): bool => $image->is_primary);
        $productThumbnail = $this->productThumbnail($product);
        $thumbnails = $product->images->where('type', 'thumbnail')->values();

        return $this->success([
            'product_id' => $product->id,
            'primary_image' => $primaryImage
                ? (new ProductImageResource($primaryImage))->resolve()
                : $productThumbnail,
            'images' => ProductImageResource::collection($product->images)->resolve(),
            'thumbnails' => $thumbnails->isNotEmpty()
                ? ProductImageResource::collection($thumbnails)->resolve()
                : ($productThumbnail ? [$productThumbnail] : []),
        ]);
    }

    public function related(Product $product, Request $request, ProductPromotionService $pricing): JsonResponse
    {
        $this->assertClientVisible($product);
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:24'],
        ]);

        $hasCategory = $product->category_id !== null;
        $hasBrand = $product->brand_id !== null;
        $limit = (int) ($validated['limit'] ?? 8);

        if (! $hasCategory && ! $hasBrand) {
            return $this->success([
                'items' => [],
                'meta' => ['limit' => $limit, 'count' => 0],
            ]);
        }

        $query = Product::query()
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->with(['category:id,name', 'brand:id,name'])
            // Star rating for each related product's card, from approved reviews only.
            ->withCount(['reviews as reviews_count' => fn (Builder $reviews) => $reviews->approved()])
            ->withAvg(['reviews as average_rating' => fn (Builder $reviews) => $reviews->approved()], 'rating')
            ->where(function (Builder $related) use ($product, $hasCategory, $hasBrand): void {
                if ($hasCategory) {
                    $related->where('category_id', $product->category_id);
                }

                if ($hasBrand) {
                    $method = $hasCategory ? 'orWhere' : 'where';
                    $related->{$method}('brand_id', $product->brand_id);
                }
            });

        if ($hasCategory) {
            $query->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$product->category_id]);
        }

        if ($hasBrand) {
            $query->orderByRaw('CASE WHEN brand_id = ? THEN 0 ELSE 1 END', [$product->brand_id]);
        }

        $products = $query
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get();
        $pricing->attachStorefrontPromotions($products);

        return $this->success([
            'items' => RelatedProductResource::collection($products)->resolve(),
            'meta' => ['limit' => $limit, 'count' => $products->count()],
        ]);
    }

    /**
     * Approved customer reviews of a product, with its rating summary.
     *
     * `summary` always covers every approved review, whatever the `rating` or
     * `verified_only` filter, so the star breakdown stays stable while a
     * shopper filters the list beneath it.
     */
    public function reviews(Product $product, ListProductReviewsRequest $request): JsonResponse
    {
        $this->assertClientVisible($product);

        $perPage = (int) $request->input('per_page', 10);
        $page = (int) $request->input('page', 1);
        [$column, $direction] = match ($request->input('sort', 'newest')) {
            'oldest' => ['created_at', 'asc'],
            'highest' => ['rating', 'desc'],
            'lowest' => ['rating', 'asc'],
            'most_helpful' => ['helpful_count', 'desc'],
            default => ['created_at', 'desc'],
        };

        $query = Review::query()
            ->approved()
            ->forProduct((string) $product->id)
            ->when($request->filled('rating'), fn (Builder $query) => $query->where('rating', (int) $request->input('rating')))
            ->when($request->boolean('verified_only'), fn (Builder $query) => $query->verified());

        $total = (clone $query)->count();
        $items = $query
            ->with('user:id,first_name,last_name,avatar')
            ->orderBy($column, $direction)
            ->orderByDesc('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return $this->success([
            'product_id' => $product->id,
            'summary' => $this->reviewSummary($product),
            'items' => ProductReviewResource::collection($items)->resolve($request),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'count' => $items->count(),
            ],
        ]);
    }

    public function specifications(Product $product): JsonResponse
    {
        $this->assertClientVisible($product);
        $product->load(['variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')]);

        return $this->success((new ProductSpecificationsResource($product))->resolve());
    }

    public function warrantyPolicies(Product $product): JsonResponse
    {
        $this->assertClientVisible($product);

        $policy = ContentItem::query()
            ->published()
            ->byType('page')
            ->where(function (Builder $query): void {
                $query->where('slug', 'warranty-policy')
                    ->orWhere('title', 'Warranty & Returns Policy');
            })
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->orderByRaw("CASE WHEN slug = 'warranty-policy' THEN 0 ELSE 1 END")
            ->first();

        return $this->success([
            'product_id' => $product->id,
            'policy_source' => $policy ? 'store' : null,
            'warranty_and_returns_policy' => $policy ? (new ContentPageResource($policy))->resolve() : null,
        ]);
    }

    /**
     * Average, count and per-star breakdown of a product's approved reviews, in
     * one grouped query.
     *
     * @return array{average_rating: float, total_reviews: int, rating_breakdown: list<array{rating: int, count: int, percentage: float}>}
     */
    private function reviewSummary(Product $product): array
    {
        $counts = Review::query()
            ->approved()
            ->forProduct((string) $product->id)
            ->selectRaw('rating, COUNT(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating')
            ->mapWithKeys(fn ($count, $rating): array => [(int) $rating => (int) $count]);

        $total = (int) $counts->sum();
        $stars = $counts->reduce(fn (int $carry, int $count, int $rating): int => $carry + $rating * $count, 0);

        return [
            'average_rating' => $total > 0 ? round($stars / $total, 1) : 0.0,
            'total_reviews' => $total,
            'rating_breakdown' => array_map(fn (int $rating): array => [
                'rating' => $rating,
                'count' => $counts->get($rating, 0),
                'percentage' => $total > 0 ? round($counts->get($rating, 0) * 100 / $total, 1) : 0.0,
            ], [5, 4, 3, 2, 1]),
        ];
    }

    private function assertClientVisible(Product $product): void
    {
        abort_unless($product->is_active, 404);
    }

    /** @return array<string, mixed>|null */
    private function productThumbnail(Product $product): ?array
    {
        if (! $product->thumbnail) {
            return null;
        }

        return [
            'id' => null,
            'product_id' => $product->id,
            'image' => $product->thumbnail,
            'url' => $product->thumbnail,
            'alt_text' => null,
            'title' => null,
            'type' => 'thumbnail',
            'is_primary' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
