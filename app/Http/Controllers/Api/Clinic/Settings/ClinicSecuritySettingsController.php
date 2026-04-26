<?php

namespace App\Http\Controllers\Api\Clinic\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Settings\RunSecurityBackupRequest;
use App\Http\Requests\Clinic\Settings\UpdateSecuritySettingsRequest;
use App\Services\Clinic\Settings\ClinicSecuritySettingsService;
use App\Support\ApiResponse;

class ClinicSecuritySettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicSecuritySettingsService $service)
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

    public function update(UpdateSecuritySettingsRequest $request)
    {
        $result = $this->service->update($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function backup(RunSecurityBackupRequest $request)
    {
        $result = $this->service->runManualBackup($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
