<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\Brand\IndexBrandsRequest;
use App\Http\Requests\Api\Admin\V1\Brand\StoreBrandRequest;
use App\Http\Requests\Api\Admin\V1\Brand\UpdateBrandRequest;
use App\Http\Resources\Api\Admin\V1\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BrandController extends BaseAdminController
{
    public function index(IndexBrandsRequest $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 25);
        $page = (int) $request->input('page', 1);
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc');
        $withTrashed = (bool) $request->input('with_trashed', false);
        $onlyTrashed = (bool) $request->input('only_trashed', false);

        $query = Brand::query();

        if ($onlyTrashed) {
            $query->onlyTrashed();
        } elseif ($withTrashed) {
            $query->withTrashed();
        }

        if ($search = $request->input('q')) {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        foreach (['is_active', 'is_featured'] as $flag) {
            if ($request->has($flag)) {
                $query->where($flag, filter_var($request->input($flag), FILTER_VALIDATE_BOOLEAN));
            }
        }

        $total = (clone $query)->count();
        $items = $query->withCount('products')
            ->orderBy($sort, $direction)
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
            'items' => BrandResource::collection($items)->resolve($request),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'count' => $items->count(),
            ],
        ]);
    }

    public function show(Brand $brand): JsonResponse
    {
        $brand->loadCount('products');

        return $this->success(new BrandResource($brand));
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug((string) $data['name']);

        $brand = DB::transaction(fn () => Brand::create($data));

        return $this->created(new BrandResource($brand));
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($brand, $data): void {
            $brand->fill($data);
            $brand->save();
        });

        return $this->success(new BrandResource($brand));
    }

    public function destroy(Brand $brand): JsonResponse
    {
        if ($brand->products()->exists()) {
            return $this->error(
                'Cannot delete a brand that still has products. Move or delete the products first.',
                422,
            );
        }

        $brand->delete();

        return $this->noContent();
    }

    public function restore(int $id): JsonResponse
    {
        /** @var Brand|null $brand */
        $brand = Brand::onlyTrashed()->findOrFail($id);
        $brand->restore();
        $brand->loadCount('products');

        return $this->success(new BrandResource($brand));
    }
}
