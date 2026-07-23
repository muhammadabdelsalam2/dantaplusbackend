<?php

namespace App\Http\Requests\Lab\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_case_alerts' => ['nullable', 'array'],
            'new_case_alerts.in_app_notification' => ['nullable', 'boolean'],
            'new_case_alerts.email_notification' => ['nullable', 'boolean'],
            'case_update_alerts' => ['nullable', 'array'],
            'case_update_alerts.in_app_notification' => ['nullable', 'boolean'],
            'case_update_alerts.email_notification' => ['nullable', 'boolean'],
        ];
    }
}
