<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Resources\Api\Client\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends BaseApiController
{
    public function index(Request $request)
    {
        $categories = Category::with('children')
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->paginate(16);

        return $this->success(CategoryResource::collection($categories));
    }

    public function show(Category $category)
    {
        $category->load('children');

        return $this->success(new CategoryResource($category));
    }
}
