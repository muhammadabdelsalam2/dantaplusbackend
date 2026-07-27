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
            'enable_system_email_notifications' => ['sometimes', 'boolean'],
            'enable_sms_whatsapp_notifications' => ['sometimes', 'boolean'],
            'notification_sounds' => ['sometimes', 'boolean'],
        ];
    }
}
