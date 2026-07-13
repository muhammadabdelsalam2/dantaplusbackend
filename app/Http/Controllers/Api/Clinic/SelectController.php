<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Services\Clinic\SelectService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class SelectController extends Controller
{
    use ApiResponse;

    public function __construct(private SelectService $service)
    {
    }

    public function show(Request $request, string $resource)
    {
        if ($resource === 'clinic_roles') {
            $roles = \App\Support\UserRoleManager::clinicRoles();

            // include 'patient' if the role exists in the DB
            if (Role::where('name', 'patient')->where('guard_name', 'web')->exists()) {
                $roles[] = 'patient';
            }

            $roles = array_values(array_unique($roles));

            return ApiResponse::success(collect($roles)
                ->map(fn ($role) => [
                    'value' => $role,
                    'label' => str($role)->replace('_', ' ')->title()->toString(),
                ])->values()->all(), 'Select options fetched successfully');
        }

        $result = $this->service->options($resource, $request->only(['search']));

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }
}
