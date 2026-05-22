<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Resources\Api\Client\V1\ShippingMethodResource;
use App\Models\ShippingMethod;
use Illuminate\Http\Request;

class ShippingMethodController extends BaseApiController
{
    public function index(Request $request)
    {
        $methods = ShippingMethod::active()
            ->ordered()
            ->get();

        return $this->success(ShippingMethodResource::collection($methods));
    }
}
