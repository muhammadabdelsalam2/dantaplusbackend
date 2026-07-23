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

        $data = $this->normalizePayload($data);

        $settings = $this->settingsRepository->getOrCreateSettings($labId, [
            'notifications_json' => self::DEFAULT_NOTIFICATIONS,
        ]);

        $updated = $this->settingsRepository->updateSettings($settings, [
            'notifications_json' => array_replace_recursive(
                self::DEFAULT_NOTIFICATIONS,
                $settings->notifications_json ?? [],
                $data
            ),
        ]);

        $fresh = $this->settingsRepository->findSettingsByLab($labId) ?? $updated->refresh();

        return ServiceResult::success($fresh->notifications_json ?? self::DEFAULT_NOTIFICATIONS, 'Notification settings updated.');
    }

    private function normalizePayload(array $data): array
    {
        $normalized = [];

        foreach (['new_case_alerts', 'case_update_alerts'] as $group) {
            if (! isset($data[$group]) || ! is_array($data[$group])) {
                continue;
            }

            foreach (['in_app_notification', 'email_notification'] as $field) {
                if (! array_key_exists($field, $data[$group])) {
                    continue;
                }

                $value = $this->toBoolean($data[$group][$field]);
                if ($value !== null) {
                    $normalized[$group][$field] = $value;
                }
            }
        }

        return $normalized;
    }

    private function toBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }
}
