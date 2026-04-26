<?php

namespace App\Http\Controllers\Api\Clinic\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Settings\UpdateGeneralSettingsRequest;
use App\Services\Clinic\Settings\GeneralSettingsService;
use App\Support\ApiResponse;

class GeneralSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private GeneralSettingsService $service)
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

    public function update(UpdateGeneralSettingsRequest $request)
    {
        $result = $this->service->update($request->validated(), $request->file('logo'));

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
