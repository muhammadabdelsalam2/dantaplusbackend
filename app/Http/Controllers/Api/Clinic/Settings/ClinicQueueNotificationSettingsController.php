<?php

namespace App\Http\Controllers\Api\Clinic\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Settings\TestQueueNotificationRequest;
use App\Http\Requests\Clinic\Settings\UpdateQueueNotificationSettingsRequest;
use App\Services\Clinic\Settings\ClinicQueueNotificationSettingsService;
use App\Support\ApiResponse;

class ClinicQueueNotificationSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicQueueNotificationSettingsService $service)
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

    public function update(UpdateQueueNotificationSettingsRequest $request)
    {
        $result = $this->service->update($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function test(TestQueueNotificationRequest $request)
    {
        $result = $this->service->test($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
