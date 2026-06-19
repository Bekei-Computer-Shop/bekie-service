<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerController extends BaseAdminController
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $customers = User::role('customer')
            ->latest()
            ->paginate(15);

        return $this->success(UserResource::collection($customers));
    }

    public function show(User $user): \Illuminate\Http\JsonResponse
    {
        return $this->success(new UserResource($user));
    }
}
