<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Services\Clinic\SelectService;
use App\Support\ApiResponse;

class SelectController extends Controller
{
    use ApiResponse;

    public function __construct(private SelectService $service)
    {
    }

    public function show(string $resource)
    {
        if ($resource === 'clinic_roles') {
            return ApiResponse::success(collect(\App\Support\UserRoleManager::clinicRoles())
                ->map(fn ($role) => [
                    'value' => $role,
                    'label' => str($role)->replace('_', ' ')->title()->toString(),
                ])->values()->all(), 'Select options fetched successfully');
        }

        $result = $this->service->options($resource);

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }
}
