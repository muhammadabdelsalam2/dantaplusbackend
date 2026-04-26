<?php

namespace App\Http\Controllers\Api\Clinic\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Settings\ConnectClinicIntegrationRequest;
use App\Services\Clinic\Settings\ClinicIntegrationSettingsService;
use App\Support\ApiResponse;

class ClinicIntegrationSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicIntegrationSettingsService $service)
    {
    }

    public function show()
    {
        $result = $this->service->show();

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function connectGoogle(ConnectClinicIntegrationRequest $request)
    {
        $result = $this->service->connect('google', $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function connectOutlook(ConnectClinicIntegrationRequest $request)
    {
        $result = $this->service->connect('outlook', $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
