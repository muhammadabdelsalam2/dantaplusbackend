<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\StoreEquipmentReportRequest;
use App\Http\Resources\Clinic\EquipmentResource;
use App\Models\Equipment;
use App\Models\OwnerMaintenanceRequest;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EquipmentController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $equipment = Equipment::query()
            ->withCount('maintenanceRequests')
            ->where('clinic_id', $clinicId)
            ->latest('id')
            ->get();

        return ApiResponse::success([
            'items' => EquipmentResource::collection($equipment)->resolve(),
        ], 'Equipment fetched successfully');
    }

    public function report(StoreEquipmentReportRequest $request, int $equipment)
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $equipmentRecord = Equipment::query()
            ->where('clinic_id', $clinicId)
            ->find($equipment);

        if (! $equipmentRecord) {
            return ApiResponse::error('Equipment not found.', 404);
        }

        $validated = $request->validated();

        $maintenanceRequest = DB::transaction(function () use ($equipmentRecord, $validated, $clinicId) {
            $requestModel = OwnerMaintenanceRequest::create([
                'request_code' => 'MR-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5)),
                'clinic_id' => $clinicId,
                'equipment_id' => $equipmentRecord->id,
                'equipment' => $equipmentRecord->name,
                'malfunction_type' => $validated['malfunction_type'],
                'issue_description' => $validated['description'],
                'urgency' => $validated['urgency'],
                'attachment_url' => $validated['attachment_url'] ?? null,
                'status' => OwnerMaintenanceRequest::STATUS_PENDING,
                'created_by' => auth()->id(),
            ]);

            if ($validated['urgency'] === 'critical') {
                $equipmentRecord->update([
                    'status' => Equipment::STATUS_BROKEN,
                ]);
            }

            return $requestModel;
        });

        return ApiResponse::success([
            'request_id' => $maintenanceRequest->id,
            'request_code' => $maintenanceRequest->request_code,
            'equipment_status' => $equipmentRecord->fresh()->status,
        ], 'Maintenance request created successfully', 201);
    }
    public function store(\Illuminate\Http\Request $request)
{
    $clinicId = auth()->user()?->clinic_id;
    if (! $clinicId) {
        return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
    }

    $validated = $request->validate([
        'name'   => 'required|string|max:255',
        'status' => 'nullable|string|in:operational,broken,under_maintenance',
    ]);

    $equipment = Equipment::create([
        'name'      => $validated['name'],
        'clinic_id' => $clinicId,
        'status'    => $validated['status'] ?? Equipment::STATUS_OPERATIONAL,
    ]);

    return ApiResponse::success(
        (new EquipmentResource($equipment->loadCount('maintenanceRequests')))->resolve(),
        'Equipment created successfully',
        201
    );
}
}
