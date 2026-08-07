<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Services\Clinic\SelectService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Models\User;

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
            // No restriction — returns rooms for everyone.
            // Optional ?clinic_id=<id> narrows it to a specific clinic's rooms.
            $clinicId = $request->query('clinic_id');

            $rooms = \App\Models\Room::query()
                ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
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

        $result = $this->service->options($resource, $request->only(['search', 'branch_id', 'clinic_id']));

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    /**
     * Dentists select — no restriction. Optional ?clinic_id=<id> in the
     * request filters to the dentists/doctors belonging to that clinic.
     */
    public function getDentists(Request $request)
    {
        $clinicId = $request->query('clinic_id');

        $dentists = User::query()
            ->with('doctor:id,user_id,specialization')
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->where(function ($query) {
                $query
                    ->whereIn('role', ['dentist', 'doctor'])
                    ->orWhereHas('roles', fn ($role) => $role->whereIn('name', ['dentist', 'doctor']));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'specialization' => $user->doctor?->specialization,
            ])
            ->values()
            ->all();

        return ApiResponse::success($dentists, 'Dentists select fetched successfully');
    }
}
