<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Services\TaxCalculator;
use Illuminate\Http\JsonResponse;

class TaxSettingsController extends BaseApiController
{
    public function show(TaxCalculator $taxCalculator): JsonResponse
    {
        return $this->success($taxCalculator->checkoutSettings());
    }
}
