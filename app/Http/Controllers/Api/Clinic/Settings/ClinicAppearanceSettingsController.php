<?php

namespace App\Http\Controllers\Api\Clinic\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Settings\UpdateAppearanceSettingsRequest;
use App\Services\Clinic\Settings\ClinicAppearanceSettingsService;
use App\Support\ApiResponse;

class ClinicAppearanceSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicAppearanceSettingsService $service)
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

    public function update(UpdateAppearanceSettingsRequest $request)
    {
        $result = $this->service->update($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
