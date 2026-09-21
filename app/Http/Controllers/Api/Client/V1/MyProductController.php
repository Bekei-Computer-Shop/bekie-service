<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Models\ProductSerial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyProductController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->success(ProductSerial::query()->where('customer_id', $request->user()->id)->with(['product', 'variant', 'order'])->latest()->paginate(20));
    }

    public function show(Request $request, ProductSerial $productSerial): JsonResponse
    {
        abort_unless((int) $productSerial->customer_id === (int) $request->user()->id, 404);

        return $this->success($productSerial->load(['product', 'variant', 'order', 'history']));
    }
}
