<?php
// UpdateNotificationSettingsRequest.php
namespace App\Http\Requests\SuperAdmin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'enable_email_notifications' => ['sometimes', 'boolean'],
            'enable_sms_whatsapp_notifications' => ['sometimes', 'boolean'],
            'notification_sounds' => ['sometimes', 'boolean'],

            // coming soon fields (store anyway)
            'twilio_sid' => ['sometimes', 'nullable', 'string', 'max:255'],
            'twilio_token' => ['sometimes', 'nullable', 'string', 'max:255'], // encrypted recommended
        ];
    }
}
