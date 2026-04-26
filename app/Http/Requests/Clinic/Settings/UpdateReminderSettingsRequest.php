<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReminderSettingsRequest extends FormRequest
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

        $times = $this->input('times');
        $timing = $this->input('timing');

        if (is_string($times)) {
            $decoded = json_decode($times, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['times' => $decoded]);
            }
        }

        if (is_string($timing)) {
            $decoded = json_decode($timing, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['timing' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'times' => ['sometimes', 'array'],
            'times.*' => ['string', 'date_format:H:i'],
            'timing' => ['sometimes', 'array'],
            'timing.*' => ['string', Rule::in(['24h', '12h', '6h', '2h', '1h', '30m'])],
            'channel' => ['sometimes', 'string', Rule::in(['whatsapp', 'sms', 'email'])],
            'template' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
