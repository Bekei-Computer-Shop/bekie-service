<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\AddressRequest;
use App\Http\Resources\Api\Client\V1\AddressResource;
use App\Models\Address;
use App\Services\CustomerAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()
            ->where('type', 'shipping')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $this->success(AddressResource::collection($addresses));
    }

    public function store(AddressRequest $request, CustomerAddressService $addressService): JsonResponse
    {
        $address = $addressService->create($request->user(), $request->validated());

        return $this->created(new AddressResource($address));
    }

    public function show(Request $request, int $addressId): JsonResponse
    {
        return $this->success(new AddressResource($this->findAddress($request, $addressId)));
    }

    public function update(AddressRequest $request, int $addressId, CustomerAddressService $addressService): JsonResponse
    {
        $address = $addressService->update($request->user(), $addressId, $request->validated());

        return $this->success(new AddressResource($address));
    }

    public function destroy(Request $request, int $addressId, CustomerAddressService $addressService): JsonResponse
    {
        $addressService->delete($request->user(), $addressId);

        return $this->noContent();
    }

    public function setDefault(Request $request, int $addressId, CustomerAddressService $addressService): JsonResponse
    {
        $address = $addressService->setDefault($request->user(), $addressId);

        return $this->success(new AddressResource($address));
    }

    private function findAddress(Request $request, int $addressId): Address
    {
        return $request->user()->addresses()
            ->where('type', 'shipping')
            ->where('is_active', true)
            ->whereKey($addressId)
            ->firstOrFail();
    }
}
