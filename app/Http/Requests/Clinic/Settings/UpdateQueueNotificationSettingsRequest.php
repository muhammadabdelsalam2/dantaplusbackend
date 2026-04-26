<?php

namespace App\Http\Requests\Clinic\Settings;

use App\Enums\WhatsAppProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQueueNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('enabled')) {
            $this->merge([
                'enabled' => filter_var($this->input('enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    ?? $this->input('enabled'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'notify_next' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'whatsapp_provider' => ['sometimes', 'string', Rule::in(array_column(WhatsAppProvider::cases(), 'value'))],
            'message_template' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
