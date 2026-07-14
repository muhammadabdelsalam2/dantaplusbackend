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

    if ($resource === 'rooms') {                     
        $clinicId = auth()->user()?->clinic_id;

        if (! $clinicId) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $rooms = \App\Models\Room::query()
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return ApiResponse::success(
            $rooms->map(fn ($room) => [
                'value' => $room->id,
                'label' => $room->name,
            ])->values()->all(),
            'Select options fetched successfully'
        );
    }

    $result = $this->service->options($resource, $request->only(['search']));

    return $result['success']
        ? ApiResponse::success($result['data'], $result['message'], $result['code'])
        : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
}
}
