<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Clinic\IndexClinicRequest;
use App\Http\Requests\Owner\Clinic\ShowClinicRequest;
use App\Http\Requests\Owner\Clinic\StoreClinicRequest;
use App\Http\Requests\Owner\Clinic\UpdateClinicRequest;
use App\Http\Requests\Owner\Clinic\UpdateClinicStatusRequest;
use App\Services\Owner\ClinicManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ClinicManagementController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicManagementService $clinicManagementService)
    {
    }

    public function index(IndexClinicRequest $request)
    {
        $result = $this->clinicManagementService->index($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreClinicRequest $request)
    {
        $result = $this->clinicManagementService->store($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(ShowClinicRequest $request, int $clinic)
    {
        $result = $this->clinicManagementService->show($clinic, $request->validated('include', ''));

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateClinicRequest $request, int $clinic)
    {
        $result = $this->clinicManagementService->update($clinic, $request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function updateStatus(UpdateClinicStatusRequest $request, int $clinic)
    {
        $result = $this->clinicManagementService->updateStatus($clinic, $request->validated('status'));

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function destroy(int $clinic)
    {
        $result = $this->clinicManagementService->destroy($clinic);

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function branches(int $clinic)
    {
        $result = $this->clinicManagementService->branches($clinic);

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

   public function clinicmodules(Request $request)
{
    return ApiResponse::success(
        config('clinic_modules.items'),
        'Clinic modules fetched successfully',
        200
    );
}
}
