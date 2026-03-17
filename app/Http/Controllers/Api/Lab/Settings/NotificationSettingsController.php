<?php

namespace App\Http\Controllers\Api\Lab\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\Settings\UpdateNotificationSettingsRequest;
use App\Services\Lab\Settings\NotificationSettingsService;
use App\Support\ApiResponse;

class NotificationSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private NotificationSettingsService $notificationSettingsService)
    {
    }

    public function show()
    {
        $result = $this->notificationSettingsService->show();

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateNotificationSettingsRequest $request)
    {
        $result = $this->notificationSettingsService->update($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
