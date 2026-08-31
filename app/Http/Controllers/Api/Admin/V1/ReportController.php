<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Services\CustomerOrdersReportService;
use App\Services\SoldProductsReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends BaseAdminController
{
    public function __construct(
        private readonly SoldProductsReportService $soldProductsService,
        private readonly CustomerOrdersReportService $customerOrdersService,
    ) {}

    public function soldProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preset' => ['nullable', 'in:daily,weekly,monthly,yearly,custom'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $payload = $this->soldProductsService->build([
            'preset' => $validated['preset'] ?? 'custom',
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ]);

        return $this->success($payload);
    }

    public function customerOrders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preset' => ['nullable', 'in:daily,weekly,monthly,yearly,custom'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $payload = $this->customerOrdersService->build([
            'preset' => $validated['preset'] ?? 'custom',
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ]);

        return $this->success($payload);
    }
}
