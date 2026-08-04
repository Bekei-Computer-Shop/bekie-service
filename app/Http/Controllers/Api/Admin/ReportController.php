<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAdminController;
use App\Services\SoldProductsReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends BaseAdminController
{
    public function __construct(private readonly SoldProductsReportService $reportService) {}

    public function soldProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preset' => ['nullable', 'in:daily,weekly,monthly,yearly,custom'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $payload = $this->reportService->build([
            'preset' => $validated['preset'] ?? 'custom',
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ]);

        return $this->success($payload);
    }
}
