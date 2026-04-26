<?php

namespace App\Http\Controllers\Api\Clinic\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Settings\TriggerReminderRequest;
use App\Http\Requests\Clinic\Settings\UpdateReminderSettingsRequest;
use App\Services\Clinic\Settings\ClinicReminderSettingsService;
use App\Support\ApiResponse;

class ClinicReminderSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicReminderSettingsService $service)
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

    public function update(UpdateReminderSettingsRequest $request)
    {
        $result = $this->service->update($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function trigger(TriggerReminderRequest $request)
    {
        $result = $this->service->trigger($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function logs()
    {
        $result = $this->service->logs();

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
