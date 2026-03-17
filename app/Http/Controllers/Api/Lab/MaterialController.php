<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\Material\IndexLabMaterialRequest;
use App\Http\Requests\Lab\Material\StoreLabMaterialRequest;
use App\Http\Requests\Lab\Material\UpdateLabMaterialRequest;
use App\Services\Lab\LabMaterialService;
use App\Support\ApiResponse;

class MaterialController extends Controller
{
    use ApiResponse;

    public function __construct(private LabMaterialService $materialService)
    {
    }

    public function index(IndexLabMaterialRequest $request)
    {
        $result = $this->materialService->listMaterials($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function lowStock(IndexLabMaterialRequest $request)
    {
        $filters = $request->validated();
        $filters['low_stock'] = true;

        $result = $this->materialService->listMaterials($filters);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function expiring(IndexLabMaterialRequest $request)
    {
        $filters = $request->validated();
        $days = (int) ($request->get('days', 30));
        $filters['expiring_before'] = now()->addDays(max(0, $days))->toDateString();

        $result = $this->materialService->listMaterials($filters);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(int $material)
    {
        $result = $this->materialService->showMaterial($material);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreLabMaterialRequest $request)
    {
        $result = $this->materialService->createMaterial($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateLabMaterialRequest $request, int $material)
    {
        $result = $this->materialService->updateMaterial($material, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function destroy(int $material)
    {
        $result = $this->materialService->deleteMaterial($material);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
