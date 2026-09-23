<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Resources\Api\Client\V1\ProductSerialResource;
use App\Models\ProductSerial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyProductController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'in:available,reserved,sold,returned,in_service,repaired,replaced,damaged,lost,cancelled'],
            'warranty' => ['sometimes', 'in:active,expired,not_covered'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ProductSerial::query()
            ->where('customer_id', $request->user()->id)
            ->with(['product', 'variant', 'order']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('warranty')) {
            $warranty = $request->input('warranty');
            if ($warranty === 'active') {
                $query->where('warranty_end_at', '>', now());
            } elseif ($warranty === 'expired') {
                $query->where('warranty_end_at', '<=', now());
            } elseif ($warranty === 'not_covered') {
                $query->whereNull('warranty_end_at');
            }
        }

        $paginator = $query->latest()->paginate($request->integer('per_page', 20));

        return $this->success([
            'items' => ProductSerialResource::collection($paginator->items())->resolve(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, ProductSerial $productSerial): JsonResponse
    {
        abort_unless((int) $productSerial->customer_id === (int) $request->user()->id, 404);

        return $this->success(new ProductSerialResource(
            $productSerial->load(['product', 'variant', 'order', 'history.actor'])
        ));
    }

    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $byStatus = ProductSerial::query()
            ->where('customer_id', $userId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $warranty = ProductSerial::query()
            ->where('customer_id', $userId)
            ->where('status', ProductSerial::SOLD)
            ->selectRaw("CASE
                WHEN warranty_end_at IS NULL THEN 'not_covered'
                WHEN warranty_end_at > NOW() THEN 'active'
                ELSE 'expired'
            END as status, count(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return $this->success([
            'by_status' => $byStatus,
            'by_warranty' => $warranty,
            'total' => ProductSerial::query()->where('customer_id', $userId)->count(),
            'active_warranty' => $warranty['active'] ?? 0,
        ]);
    }
}
