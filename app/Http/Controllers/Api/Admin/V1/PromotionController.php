<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\StorePromotionRequest;
use App\Http\Requests\Api\Admin\V1\UpdatePromotionRequest;
use App\Http\Resources\Api\Admin\V1\PromotionResource;
use App\Models\Coupon;
use App\Models\CustomerGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PromotionController extends BaseAdminController
{
    /** Enough of each target for the edit form to show what is selected. */
    private const TARGET_RELATIONS = [
        'products:id,category_id,name,sku,short_description,price,sale_price,thumbnail',
        'products.category:id,name',
        'categories:id,parent_id,name',
        'categories.parent:id,name',
        'variants:id,product_id,name,sku',
        'customerGroups:id,name,description',
    ];

    public function index(): JsonResponse
    {
        $promotions = Coupon::query()->latest()->paginate(15);

        return $this->success(PromotionResource::collection($promotions));
    }

    public function show(Coupon $promotion): JsonResponse
    {
        return $this->success(new PromotionResource($promotion->load(self::TARGET_RELATIONS)));
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $targets = $this->extractTargets($data);

        $promotion = DB::transaction(function () use ($data, $targets): Coupon {
            $promotion = Coupon::create($data);
            $this->syncTargets($promotion, $targets);

            return $promotion;
        });

        return $this->created(new PromotionResource($promotion->load(self::TARGET_RELATIONS)));
    }

    public function update(UpdatePromotionRequest $request, Coupon $promotion): JsonResponse
    {
        $data = $request->validated();
        $targets = $this->extractTargets($data, $promotion);

        DB::transaction(function () use ($promotion, $data, $targets): void {
            $promotion->update($data);
            $this->syncTargets($promotion, $targets);
        });

        return $this->success(new PromotionResource($promotion->load(self::TARGET_RELATIONS)));
    }

    public function destroy(Coupon $promotion): JsonResponse
    {
        $promotion->delete();

        return $this->noContent();
    }

    public function customerGroups(): JsonResponse
    {
        return $this->success(CustomerGroup::query()
            ->where('is_active', true)
            ->withCount('users')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']));
    }

    /**
     * Pulls the target ids out of the validated payload. Returns null when an
     * update leaves targeting out, so the saved targets stay as they are.
     *
     * @param  array<string, mixed>  $data
     * @return array{type: string, ids: array<string, list<int|string>>}|null
     */
    private function extractTargets(array &$data, ?Coupon $promotion = null): ?array
    {
        $touched = array_key_exists('target_type', $data);
        $ids = [];

        foreach (Coupon::TARGET_FIELDS as $fields) {
            foreach (array_keys($fields) as $field) {
                if (array_key_exists($field, $data)) {
                    $touched = true;
                    $ids[$field] = array_values(array_unique($data[$field] ?? []));
                }
                unset($data[$field]);
            }
        }

        if (! $touched) {
            return null;
        }

        // Validation guarantees a type here: sent, or already on the promotion.
        $data['target_type'] ??= $promotion?->target_type;

        if ($data['target_type'] !== Coupon::TARGET_CATEGORIES) {
            $data['include_subcategories'] = false;
        }

        return ['type' => $data['target_type'], 'ids' => $ids];
    }

    /**
     * Syncs the chosen target type and detaches every other one, so switching
     * a promotion from products to categories leaves no products behind.
     *
     * @param  array{type: string, ids: array<string, list<int|string>>}|null  $targets
     */
    private function syncTargets(Coupon $promotion, ?array $targets): void
    {
        if ($targets === null) {
            return;
        }

        foreach (Coupon::TARGET_FIELDS as $type => $fields) {
            foreach ($fields as $field => $relation) {
                if ($type !== $targets['type']) {
                    $promotion->{$relation}()->sync([]);
                } elseif (array_key_exists($field, $targets['ids'])) {
                    $promotion->{$relation}()->sync($targets['ids'][$field]);
                }
            }
        }
    }
}
