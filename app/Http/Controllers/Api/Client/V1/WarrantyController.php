<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Models\ProductSerial;
use App\Services\ProductSerialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarrantyController extends BaseApiController
{
    public function __construct(private readonly ProductSerialService $serials) {}

    public function validate(Request $request, string $serialNumber): JsonResponse
    {
        try {
            $serial = ProductSerial::query()
                ->where('serial_number', $serialNumber)
                ->where('customer_id', $request->user()->id)
                ->firstOrFail();

            return $this->success($this->serials->validateWarranty($serial));
        } catch (\Exception) {
            return $this->error("We couldn't find a product with this serial number. Please check the number and try again.", 404, code: 'serial_not_found');
        }
    }

    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return $this->success([
            'total_products' => ProductSerial::query()->where('customer_id', $userId)->count(),
            'products_under_warranty' => ProductSerial::query()
                ->where('customer_id', $userId)
                ->where('status', ProductSerial::SOLD)
                ->where('warranty_end_at', '>', now())
                ->count(),
            'expiring_soon' => ProductSerial::query()
                ->where('customer_id', $userId)
                ->where('status', ProductSerial::SOLD)
                ->whereBetween('warranty_end_at', [now(), now()->addDays(30)])
                ->count(),
            'expired_warranty' => ProductSerial::query()
                ->where('customer_id', $userId)
                ->where('status', ProductSerial::SOLD)
                ->where('warranty_end_at', '<=', now())
                ->count(),
        ]);
    }
}
