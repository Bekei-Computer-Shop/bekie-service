<?php

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\StoreProductSerialRequest;
use App\Http\Requests\Api\Admin\V1\UpdateProductSerialRequest;
use App\Models\ProductSerial;
use App\Services\ProductSerialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSerialController extends BaseAdminController
{
    public function __construct(private readonly ProductSerialService $serials) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->serials->query($request->only(['q', 'status', 'product_id', 'customer_id', 'order_id']))
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return $this->success([
            'items' => $paginator->items(),
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
                $request->validated('warehouse'),
                $request->validated('receiving_reference'),
                $request->user()->id,
                $request->validated('notes'),
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->created(['items' => $created], 'Serial numbers received.');
    }

    public function show(ProductSerial $productSerial): JsonResponse
    {
        return $this->success($productSerial->load(['product', 'variant', 'customer', 'order', 'history.actor']));
    }

    public function lookup(string $serialNumber): JsonResponse
    {
        $serial = ProductSerial::query()->where('serial_number', $serialNumber)->firstOrFail();

        return $this->success($serial->load(['product', 'variant', 'customer', 'order']));
    }

    public function update(UpdateProductSerialRequest $request, ProductSerial $productSerial): JsonResponse
    {
        $data = $request->validated();
        try {
            if (isset($data['status'])) {
                $productSerial = $this->serials->transition($productSerial, $data['status'], $request->user()->id, null, $data['notes'] ?? null);
                unset($data['status'], $data['notes']);
            }
            if ($data !== []) {
                $productSerial->update($data);
            }
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success($productSerial->fresh(['product', 'variant', 'customer', 'order']));
    }

    public function history(ProductSerial $productSerial): JsonResponse
    {
        return $this->success($productSerial->load('history.actor', 'history.order')->history);
    }

    public function summary(): JsonResponse
    {
        return $this->success(ProductSerial::query()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status'));
    }
}
