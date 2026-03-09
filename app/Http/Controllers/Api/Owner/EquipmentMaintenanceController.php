<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Maintenance\IndexMaintenanceCompaniesRequest;
use App\Http\Requests\Owner\Maintenance\IndexMaintenanceRequestsRequest;
use App\Http\Requests\Owner\Maintenance\ReviewMaintenanceAlertRequest;
use App\Http\Requests\Owner\Maintenance\StoreMaintenanceCompanyRequest;
use App\Http\Requests\Owner\Maintenance\StoreMaintenanceRequest;
use App\Http\Requests\Owner\Maintenance\UpdateMaintenanceRequest;
use App\Services\Owner\EquipmentMaintenanceService;
use App\Support\ApiResponse;

class EquipmentMaintenanceController extends Controller
{
    use ApiResponse;

    public function __construct(private EquipmentMaintenanceService $service) {}

    public function listRequests(IndexMaintenanceRequestsRequest $request)
    {
        $result = $this->service->listRequests($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function storeRequest(StoreMaintenanceRequest $request)
    {
        $result = $this->service->createRequest($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function updateRequest(UpdateMaintenanceRequest $request, int $id)
    {
        $result = $this->service->updateRequest($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function listCompanies(IndexMaintenanceCompaniesRequest $request)
    {
        $result = $this->service->listCompanies($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function storeCompany(StoreMaintenanceCompanyRequest $request)
    {
        $result = $this->service->createCompany($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function reviewAlert(ReviewMaintenanceAlertRequest $request, int $id)
    {
        $result = $this->service->reviewAlert($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
