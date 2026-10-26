<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Resources\Api\Client\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

class CategoryController extends BaseApiController
{
    public function index(Request $request)
    {
        $categories = Category::withCount([
            'products' => static fn (Builder $query) => $query->where('is_active', true),
        ])
            ->with(['children' => static fn (HasMany $query) => $query
                ->where('is_active', true)
                ->withCount(['products' => static fn (Builder $productQuery) => $productQuery->where('is_active', true)])])
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->paginate(16);

        return $this->success(CategoryResource::collection($categories));
    }

    public function show(Category $category)
    {
        $category->loadCount([
            'products' => static fn (Builder $query) => $query->where('is_active', true),
        ])->load(['children' => static fn (HasMany $query) => $query
            ->where('is_active', true)
            ->withCount(['products' => static fn (Builder $productQuery) => $productQuery->where('is_active', true)])]);

        return $this->success(new CategoryResource($category));
    }
}
