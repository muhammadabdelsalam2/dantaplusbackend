<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\SyncRolePermissionsRequest;
use App\Services\Access\RoleAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AccessController extends Controller
{
    use ApiResponse;

    public function __construct(private RoleAccessService $service)
    {
    }

    public function me(Request $request)
    {
        $result = $this->service->currentUserAccess($request->user());

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function permissionsMatrix()
    {
        $result = $this->service->permissionsMatrix();

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function syncRolePermissions(SyncRolePermissionsRequest $request, string $roleId)
    {
        $result = $this->service->syncRolePermissions(
            $request->user(),
            $roleId,
            $request->validated()['modules']
        );

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
