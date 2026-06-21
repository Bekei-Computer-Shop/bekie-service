<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Resources\Admin\PermissionResource;
use Spatie\Permission\Models\Permission;

class PermissionController extends BaseAdminController
{
    public function index()
    {
        $permissions = Permission::orderBy('name')->get();

        return $this->success(PermissionResource::collection($permissions));
    }
}
