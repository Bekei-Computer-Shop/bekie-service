<?php

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Resources\Api\Admin\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin APIs
 *
 * @subgroup Profile Management
 * @authenticated
 */
class ProfileController extends BaseAdminController
{
    /**
     * Get Authenticated Admin User
     *
     * Retrieves the profile information for the currently authenticated admin user.
     *
     * @responseFile 200 storage/responses/admin/user.json
     */
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return $this->success(
            data: UserResource::make($user->load('roles'))
        );
    }
}
