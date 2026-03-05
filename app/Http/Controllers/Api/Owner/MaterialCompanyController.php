<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Material\IndexMaterialCompanyRequest;
use App\Http\Requests\Owner\Material\ShowMaterialCompanyRequest;
use App\Http\Requests\Owner\Material\StoreMaterialCompanyRequest;
use App\Http\Requests\Owner\Material\UpdateMaterialCompanyCommissionRequest;
use App\Http\Requests\Owner\Material\UpdateMaterialCompanyRequest;
use App\Http\Requests\Owner\Material\UpdateMaterialCompanyStatusRequest;
use App\Services\Owner\MaterialCompanyService;
use App\Support\ApiResponse;

class MaterialCompanyController extends Controller
{
    use ApiResponse;

    public function __construct(private MaterialCompanyService $materialCompanyService)
    {
    }

    public function index(IndexMaterialCompanyRequest $request)
    {
        $result = $this->materialCompanyService->index($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreMaterialCompanyRequest $request)
    {
        $result = $this->materialCompanyService->store($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(ShowMaterialCompanyRequest $request, int $company)
    {
        $result = $this->materialCompanyService->show($company);

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateMaterialCompanyRequest $request, int $company)
    {
        $result = $this->materialCompanyService->update($company, $request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function updateStatus(UpdateMaterialCompanyStatusRequest $request, int $company)
    {
        $result = $this->materialCompanyService->updateStatus($company, $request->validated('status'));

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function updateCommission(UpdateMaterialCompanyCommissionRequest $request, int $company)
    {
        $result = $this->materialCompanyService->updateCommission($company, (float) $request->validated('commission_percentage'));

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function destroy(int $company)
    {
        $result = $this->materialCompanyService->destroy($company);

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
