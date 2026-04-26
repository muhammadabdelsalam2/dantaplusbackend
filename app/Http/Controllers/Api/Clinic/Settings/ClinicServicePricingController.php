<?php

namespace App\Http\Controllers\Api\Clinic\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Settings\StoreServicePricingRequest;
use App\Http\Requests\Clinic\Settings\UpdateServicePricingRequest;
use App\Services\Clinic\Settings\ClinicServicePricingService;
use App\Support\ApiResponse;

class ClinicServicePricingController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicServicePricingService $service)
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

    public function store(StoreServicePricingRequest $request)
    {
        $result = $this->service->store($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateServicePricingRequest $request, int $serviceId)
    {
        $result = $this->service->update($serviceId, $request->validated());

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
