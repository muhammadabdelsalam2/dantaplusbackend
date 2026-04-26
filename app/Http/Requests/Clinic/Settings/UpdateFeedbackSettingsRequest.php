<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeedbackSettingsRequest extends FormRequest
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

        $channels = $this->input('channels');
        if (is_string($channels)) {
            $decoded = json_decode($channels, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['channels' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => ['string', Rule::in(['sms', 'whatsapp'])],
            'delay_minutes' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'message_template' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'custom_link' => ['sometimes', 'nullable', 'url', 'max:1000'],
        ];
    }
}
