<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Role\StoreRoleRequest;
use App\Http\Requests\SuperAdmin\Role\SyncRolePermissionsRequest;
use App\Http\Requests\SuperAdmin\Role\UpdateRoleRequest;
use App\Http\Resources\SuperAdmin\RoleResource;
use App\Services\SuperAdmin\RoleManagementService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private RoleManagementService $service) {}

    // GET /api/superadmin/roles?q=
    public function index(Request $request)
    {
        $roles = $this->service->list($request->query('q'));

        return response()->json([
            'success' => true,
            'data' => RoleResource::collection($roles),
        ]);
    }

    // POST /api/superadmin/roles
    public function store(StoreRoleRequest $request)
    {
        $role = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => new RoleResource($role),
        ], 201);
    }

    // GET /api/superadmin/roles/{role}
    public function show(Role $role)
    {
        $role = $this->service->show($role);

        return response()->json([
            'success' => true,
            'data' => new RoleResource($role),
        ]);
    }

    // PATCH /api/superadmin/roles/{role}
    public function update(UpdateRoleRequest $request, Role $role)
    {
        try {
            $updated = $this->service->update($role, $request->validated());

            return response()->json([
                'success' => true,
                'data' => new RoleResource($updated),
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    // DELETE /api/superadmin/roles/{role}
    public function destroy(Role $role)
    {
        try {
            $this->service->delete($role);

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully',
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    // PUT /api/superadmin/roles/{role}/permissions
    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role)
    {
        try {
            $role = $this->service->syncPermissions($role, $request->validated()['permissions']);

            return response()->json([
                'success' => true,
                'data' => new RoleResource($role),
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
