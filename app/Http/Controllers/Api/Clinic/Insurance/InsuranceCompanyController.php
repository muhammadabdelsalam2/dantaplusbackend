<?php

namespace App\Http\Controllers\Api\Clinic\Insurance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Insurance\StoreInsuranceCompanyRequest;
use App\Http\Requests\Clinic\Insurance\UpdateInsuranceCompanyRequest;
use App\Services\Clinic\Insurance\InsuranceCompanyService;
use App\Support\ApiResponse;

class InsuranceCompanyController extends Controller
{
    use ApiResponse;

    public function __construct(private InsuranceCompanyService $service)
    {
    }

    public function index()
    {
        $result = $this->service->index();

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreInsuranceCompanyRequest $request)
    {
        $result = $this->service->store($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(int $id)
    {
        $result = $this->service->show($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateInsuranceCompanyRequest $request, int $id)
    {
        $result = $this->service->update($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function destroy(int $id)
    {
        $result = $this->service->destroy($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
