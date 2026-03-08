<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Lab\BulkDentalLabDeleteRequest;
use App\Http\Requests\Owner\Lab\BulkDentalLabStatusRequest;
use App\Http\Requests\Owner\Lab\IndexDentalLabRequest;
use App\Http\Requests\Owner\Lab\ShowDentalLabRequest;
use App\Http\Requests\Owner\Lab\StoreDentalLabRequest;
use App\Http\Requests\Owner\Lab\UpdateDentalLabRequest;
use App\Http\Requests\Owner\Lab\UpdateDentalLabStatusRequest;
use App\Services\Owner\DentalLabManagementService;
use App\Support\ApiResponse;

class DentalLabManagementController extends Controller
{
    use ApiResponse;

    public function __construct(private DentalLabManagementService $dentalLabManagementService)
    {
    }

    public function index(IndexDentalLabRequest $request)
    {
        $result = $this->dentalLabManagementService->index($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreDentalLabRequest $request)
    {
        $result = $this->dentalLabManagementService->store($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(ShowDentalLabRequest $request, int $lab)
    {
        $result = $this->dentalLabManagementService->show($lab, $request->validated('include', ''));

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateDentalLabRequest $request, int $lab)
    {
        $result = $this->dentalLabManagementService->update($lab, $request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function destroy(int $lab)
    {
        $result = $this->dentalLabManagementService->destroy($lab);

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function updateStatus(UpdateDentalLabStatusRequest $request, int $lab)
    {
        $result = $this->dentalLabManagementService->updateStatus($lab, $request->validated('status'));

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function bulkStatus(BulkDentalLabStatusRequest $request)
    {
        $result = $this->dentalLabManagementService->bulkStatus(
            $request->validated('ids', []),
            $request->validated('status')
        );

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function bulkDelete(BulkDentalLabDeleteRequest $request)
    {
        $result = $this->dentalLabManagementService->bulkDelete($request->validated('ids', []));

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
