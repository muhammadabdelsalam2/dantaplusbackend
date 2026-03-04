<?php

namespace App\Http\Controllers\Api\SuperAdmin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Settings\UpdateNotificationSettingsRequest;
use App\Services\SuperAdmin\SettingsService;
use App\Support\ApiResponse;

class NotificationSettingsController extends Controller
{
    use ApiResponse;

    private const GROUP = 'notifications';

    public function __construct(private SettingsService $settingsService)
    {
    }

    public function show()
    {
        $data = $this->settingsService->getGroup(self::GROUP);

        // mask token if exists
        if (!empty($data['twilio_token']) && is_string($data['twilio_token'])) {
            $data['twilio_token_masked'] = substr($data['twilio_token'], 0, 4) . '****' . substr($data['twilio_token'], -4);
            unset($data['twilio_token']);
        }

        return ApiResponse::success($data);
    }

    public function update(UpdateNotificationSettingsRequest $request)
    {
        $data = $this->settingsService->updateGroup(self::GROUP, $request->validated(), encryptedKeys: ['twilio_token']);

        if (!empty($data['twilio_token']) && is_string($data['twilio_token'])) {
            $data['twilio_token_masked'] = substr($data['twilio_token'], 0, 4) . '****' . substr($data['twilio_token'], -4);
            unset($data['twilio_token']);
        }

        return ApiResponse::success($data, 'Notification settings updated');
    }
}
