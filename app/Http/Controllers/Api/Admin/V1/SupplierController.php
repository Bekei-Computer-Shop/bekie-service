<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\Supplier\IndexSuppliersRequest;
use App\Http\Requests\Api\Admin\V1\Supplier\StoreSupplierRequest;
use App\Http\Requests\Api\Admin\V1\Supplier\UpdateSupplierRequest;
use App\Http\Resources\Api\Admin\V1\SupplierResource;
use App\Models\Supplier;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

#[Group(name: 'Suppliers', description: 'Suppliers stock is received from, and the store-wide default supplier.')]
class SupplierController extends BaseAdminController
{
    public function index(IndexSuppliersRequest $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 25);
        $page = (int) $request->input('page', 1);
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc');

        $query = Supplier::query();

        if ($request->boolean('only_trashed')) {
            $query->onlyTrashed();
        } elseif ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        if ($search = $request->input('q')) {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('contact_person', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
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
            'items' => SupplierResource::collection($items)->resolve($request),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'count' => $items->count(),
            ],
        ]);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->loadCount(['products', 'stockMovements']);

        return $this->success(new SupplierResource($supplier));
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $data = $request->validated();
        // The model doesn't see the column defaults before insert, so set them here.
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['is_default'] = (bool) ($data['is_default'] ?? false);

        $supplier = DB::transaction(function () use ($data): Supplier {
            $supplier = new Supplier($data);
            $this->saveKeepingOneDefault($supplier);

            return $supplier;
        });

        return $this->created(new SupplierResource($supplier));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        DB::transaction(function () use ($request, $supplier): void {
            $supplier->fill($request->validated());
            $this->saveKeepingOneDefault($supplier);
        });

        return $this->success(new SupplierResource($supplier));
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        if ($supplier->products()->exists()) {
            return $this->error(
                "This supplier is still the default supplier for some products, so it can't be deleted. Change their default supplier or deactivate this supplier instead.",
                422,
                code: 'supplier_has_products',
            );
        }

        // Soft delete: stock history keeps pointing at the row and still shows its name.
        $supplier->is_default = false;
        $supplier->save();
        $supplier->delete();

        return $this->noContent();
    }

    /**
     * Only an active supplier can be the store default, and there is at most
     * one: marking this one clears the flag everywhere else.
     */
    private function saveKeepingOneDefault(Supplier $supplier): void
    {
        if (! $supplier->is_active) {
            $supplier->is_default = false;
        }

        $supplier->save();

        if ($supplier->is_default) {
            Supplier::query()
                ->whereKeyNot($supplier->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }
}
