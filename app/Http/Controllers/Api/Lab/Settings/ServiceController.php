<?php

namespace App\Http\Controllers\Api\Lab\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\Settings\StoreServiceRequest;
use App\Http\Requests\Lab\Settings\UpdateServiceRequest;
use App\Services\Lab\Settings\ServiceService;
use App\Support\ApiResponse;

class ServiceController extends Controller
{
    use ApiResponse;

    public function __construct(private ServiceService $serviceService)
    {
    }

    public function index()
    {
        $result = $this->serviceService->listServices();

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreServiceRequest $request)
    {
        $result = $this->serviceService->createService($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateServiceRequest $request, int $service)
    {
        $result = $this->serviceService->updateService($service, $request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function destroy(int $service)
    {
        $result = $this->serviceService->deleteService($service);

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
