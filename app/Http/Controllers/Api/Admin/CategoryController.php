<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\Admin\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends BaseAdminController
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return $this->success(CategoryResource::collection($categories));
    }

    public function show(Category $category): \Illuminate\Http\JsonResponse
    {
        return $this->success(new CategoryResource($category->load('children')));
    }

    public function store(StoreCategoryRequest $request): \Illuminate\Http\JsonResponse
    {
        $category = Category::create($request->validated());

        return $this->created(new CategoryResource($category));
    }

    public function update(UpdateCategoryRequest $request, Category $category): \Illuminate\Http\JsonResponse
    {
        $category->update($request->validated());

        return $this->success(new CategoryResource($category));
    }

    public function destroy(Category $category): \Illuminate\Http\JsonResponse
    {
        $category->delete();

        return $this->noContent();
    }
}
