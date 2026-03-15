<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\Equipment\IndexLabEquipmentRequest;
use App\Http\Requests\Lab\Equipment\RecordLabEquipmentMaintenanceRequest;
use App\Http\Requests\Lab\Equipment\StoreLabEquipmentRequest;
use App\Http\Requests\Lab\Equipment\UpdateLabEquipmentRequest;
use App\Services\Lab\LabEquipmentService;
use App\Support\ApiResponse;

class LabEquipmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LabEquipmentService $labEquipmentService
    ) {
    }

    public function index(IndexLabEquipmentRequest $request)
    {
        $result = $this->labEquipmentService->index($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreLabEquipmentRequest $request)
    {
        $result = $this->labEquipmentService->store($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(int $id)
    {
        $result = $this->labEquipmentService->show($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateLabEquipmentRequest $request, int $id)
    {
        $result = $this->labEquipmentService->update($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function destroy(int $id)
    {
        $result = $this->labEquipmentService->destroy($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function recordMaintenance(RecordLabEquipmentMaintenanceRequest $request, int $id)
    {
        $result = $this->labEquipmentService->recordMaintenance($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
