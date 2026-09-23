<?php

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\StoreProductSerialRequest;
use App\Http\Requests\Api\Admin\V1\UpdateProductSerialRequest;
use App\Http\Resources\Api\Admin\V1\ProductSerialHistoryResource;
use App\Http\Resources\Api\Admin\V1\ProductSerialResource;
use App\Models\ProductSerial;
use App\Services\ProductSerialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductSerialController extends BaseAdminController
{
    public function __construct(private readonly ProductSerialService $serials) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->serials->query($request->only([
            'q', 'status', 'product_id', 'product_variant_id', 'warehouse',
            'customer_id', 'order_id', 'received_from', 'received_to',
        ]))
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return $this->success([
            'items' => ProductSerialResource::collection($paginator->items())->resolve(),
            'pagination' => [
                'total' => $paginator->total(), 'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreProductSerialRequest $request): JsonResponse
    {
        try {
            $created = $this->serials->receive(
                $request->validated('serial_numbers'),
                $request->validated('product_id'),
                $request->validated('product_variant_id'),
                null,
                $request->validated('receiving_reference'),
                $request->user()->id,
                $request->validated('notes'),
                $request->validated('receiving_quantity'),
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->created([
            'items' => ProductSerialResource::collection($created)->resolve(),
        ], 'Serial numbers received.');
    }

    public function show(ProductSerial $productSerial): JsonResponse
    {
        return $this->success(new ProductSerialResource(
            $productSerial->load(['product', 'variant', 'customer', 'order', 'history.actor', 'history.order'])
        ));
    }

    public function lookup(string $serialNumber): JsonResponse
    {
        $serial = ProductSerial::query()
            ->where('serial_number', $serialNumber)
            ->with(['product', 'variant', 'customer', 'order'])
            ->firstOrFail();

        return $this->success(new ProductSerialResource($serial));
    }

    public function update(UpdateProductSerialRequest $request, ProductSerial $productSerial): JsonResponse
    {
        $data = $request->validated();
        $note = $data['notes'] ?? null;
        try {
            if (isset($data['status'])) {
                $productSerial = $this->serials->transition($productSerial, $data['status'], $request->user()->id, null, $note);
                unset($data['status']);
            }
            unset($data['notes']);
            if ($data !== []) {
                $productSerial->update($data);
            }
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(new ProductSerialResource(
            $productSerial->fresh(['product', 'variant', 'customer', 'order'])
        ));
    }

    public function history(ProductSerial $productSerial): JsonResponse
    {
        return $this->success(
            ProductSerialHistoryResource::collection(
                $productSerial->load('history.actor', 'history.order')->history
            )->resolve()
        );
    }

    public function summary(): JsonResponse
    {
        $byStatus = ProductSerial::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return $this->success([
            'by_status' => $byStatus,
            'total' => ProductSerial::query()->count(),
            'available' => ProductSerial::query()->where('status', ProductSerial::AVAILABLE)->count(),
            'sold' => ProductSerial::query()->where('status', ProductSerial::SOLD)->count(),
            'reserved' => ProductSerial::query()->where('status', ProductSerial::RESERVED)->count(),
            'warranty_expiring_soon' => ProductSerial::query()
                ->where('status', ProductSerial::SOLD)
                ->whereBetween('warranty_end_at', [now(), now()->addDays(30)])
                ->count(),
        ]);
    }

    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'serial_ids' => ['required', 'array', 'min:1'],
                'serial_ids.*' => ['integer'],
                'status' => ['required', 'in:available,reserved,sold,returned,in_service,repaired,replaced,damaged,lost,cancelled'],
            ]);

            $count = 0;
            foreach ($request->input('serial_ids') as $serialId) {
                try {
                    $serial = ProductSerial::findOrFail($serialId);
                    $this->serials->transition($serial, $request->input('status'), $request->user()->id);
                    $count++;
                } catch (\InvalidArgumentException) {
                    continue;
                }
            }

            return $this->success(['updated_count' => $count], "Updated {$count} serial(s).");
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }

    public function export(Request $request): JsonResponse
    {
        $serials = $this->serials->query($request->only([
            'q', 'status', 'product_id', 'product_variant_id', 'warehouse',
            'customer_id', 'order_id', 'received_from', 'received_to',
        ]))->get();

        $data = ProductSerialResource::collection($serials)->resolve();

        return $this->success([
            'total' => count($data),
            'data' => $data,
        ]);
    }

    public function warrantyStats(): JsonResponse
    {
        return $this->success($this->serials->getWarrantyStats());
    }

    public function validateWarranty(string $serialNumber): JsonResponse
    {
        try {
            $serial = ProductSerial::query()
                ->where('serial_number', $serialNumber)
                ->firstOrFail();

            return $this->success($this->serials->validateWarranty($serial));
        } catch (\Exception) {
            return $this->error('Serial number not found', 404);
        }
    }

    public function setWarranty(Request $request, ProductSerial $productSerial): JsonResponse
    {
        try {
            $request->validate([
                'days' => ['required', 'integer', 'min:1', 'max:1825'],
            ]);

            $updated = $this->serials->setWarrantyPeriod($productSerial, $request->integer('days'), $request->user()->id);

            return $this->success(new ProductSerialResource($updated), 'Warranty period set.');
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }
}
