<?php

namespace App\Http\Requests\Lab\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_case_alerts' => ['sometimes', 'nullable', 'array'],
            'new_case_alerts.in_app_notification' => ['sometimes', 'nullable', 'boolean'],
            'new_case_alerts.email_notification' => ['sometimes', 'nullable', 'boolean'],
            'case_update_alerts' => ['sometimes', 'nullable', 'array'],
            'case_update_alerts.in_app_notification' => ['sometimes', 'nullable', 'boolean'],
            'case_update_alerts.email_notification' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach ($this->notificationKeys() as $key) {
            if (array_key_exists($key, $data)) {
                Arr::set($data, $key, $this->toBoolean($data[$key]));
                unset($data[$key]);
            }
        }

        foreach (['new_case_alerts', 'case_update_alerts'] as $group) {
            if (! isset($data[$group]) || ! is_array($data[$group])) {
                continue;
            }

            foreach (['in_app_notification', 'email_notification'] as $field) {
                if (array_key_exists($field, $data[$group])) {
                    $data[$group][$field] = $this->toBoolean($data[$group][$field]);
                }
            }
        }

        $this->replace($data);
    }

    private function notificationKeys(): array
    {
        return [
            'new_case_alerts.in_app_notification',
            'new_case_alerts.email_notification',
            'case_update_alerts.in_app_notification',
            'case_update_alerts.email_notification',
        ];
    }

    private function toBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
