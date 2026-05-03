<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Maintenance\UpdateMaintenanceRequestStatusRequest;
use App\Http\Resources\SuperAdmin\MaintenanceRequestResource;
use App\Models\OwnerMaintenanceRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MaintenanceRequestController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $requests = OwnerMaintenanceRequest::query()
            ->with(['clinic:id,name', 'company:id,name', 'equipmentRecord:id,name'])
            ->latest('id')
            ->paginate(max(1, min((int) $request->integer('per_page', 15), 100)));

        return ApiResponse::success([
            'items' => MaintenanceRequestResource::collection($requests->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ], 'Maintenance requests fetched successfully');
    }

    public function update(UpdateMaintenanceRequestStatusRequest $request, int $id)
    {
        $maintenanceRequest = OwnerMaintenanceRequest::query()
            ->with(['clinic:id,name', 'company:id,name', 'equipmentRecord:id,name'])
            ->find($id);

        if (! $maintenanceRequest) {
            return ApiResponse::error('Maintenance request not found.', 404);
        }

        $maintenanceRequest->update($request->validated());

        return ApiResponse::success((new MaintenanceRequestResource($maintenanceRequest->fresh(['clinic:id,name', 'company:id,name', 'equipmentRecord:id,name'])))->resolve(), 'Maintenance request updated successfully');
    }
}
