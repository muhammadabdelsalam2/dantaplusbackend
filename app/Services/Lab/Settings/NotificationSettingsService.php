<?php

namespace App\Services\Lab\Settings;

use App\Repositories\Lab\Settings\SettingsRepositoryInterface;
use App\Support\ServiceResult;

class NotificationSettingsService
{
    private const DEFAULT_NOTIFICATIONS = [
        'new_case_alerts' => [
            'in_app_notification' => true,
            'email_notification' => false,
        ],
        'case_update_alerts' => [
            'in_app_notification' => true,
            'email_notification' => false,
        ],
    ];

    public function __construct(private SettingsRepositoryInterface $settingsRepository)
    {
    }

    public function show(): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $settings = $this->settingsRepository->getOrCreateSettings($labId, [
            'notifications_json' => self::DEFAULT_NOTIFICATIONS,
        ]);

        return ServiceResult::success($settings->notifications_json ?? self::DEFAULT_NOTIFICATIONS, 'Notification settings fetched successfully');
    }

    public function update(array $data): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $settings = $this->settingsRepository->getOrCreateSettings($labId, [
            'notifications_json' => self::DEFAULT_NOTIFICATIONS,
        ]);

        $updated = $this->settingsRepository->updateSettings($settings, [
            'notifications_json' => $data,
        ]);

        return ServiceResult::success($updated->notifications_json, 'Notification settings updated.');
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }
}
