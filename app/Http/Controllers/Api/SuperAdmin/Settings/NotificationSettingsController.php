<?php

namespace App\Http\Controllers\Api\SuperAdmin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Settings\UpdateNotificationSettingsRequest;
use App\Services\SuperAdmin\SettingsService;
use App\Support\ApiResponse;

class NotificationSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private SettingsService $settingsService)
    {
    }

    public function show()
    {
        return ApiResponse::success($this->settingsService->notificationSettings());
    }

    public function update(UpdateNotificationSettingsRequest $request)
    {
        $this->settingsService->updateGroup('notifications', $request->validated());

        return ApiResponse::success(
            $this->settingsService->notificationSettings(),
            'Notification settings updated'
        );
    }
}
