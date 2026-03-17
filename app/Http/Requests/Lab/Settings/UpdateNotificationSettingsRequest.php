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
            'new_case_alerts' => ['required', 'array'],
            'new_case_alerts.in_app_notification' => ['required', 'boolean'],
            'new_case_alerts.email_notification' => ['required', 'boolean'],
            'case_update_alerts' => ['required', 'array'],
            'case_update_alerts.in_app_notification' => ['required', 'boolean'],
            'case_update_alerts.email_notification' => ['required', 'boolean'],
        ];
    }
}
