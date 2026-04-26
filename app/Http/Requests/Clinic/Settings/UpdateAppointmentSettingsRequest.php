<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['allow_overbooking', 'allow_overlap', 'auto_confirm', 'send_reminders'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                        ?? $this->input($field),
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'default_duration' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'slot_duration' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'buffer_time' => ['sometimes', 'integer', 'min:0', 'max:180'],
            'max_advance_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'allow_overlap' => ['sometimes', 'boolean'],
            'allow_overbooking' => ['sometimes', 'boolean'],
            'auto_confirm' => ['sometimes', 'boolean'],
            'send_reminders' => ['sometimes', 'boolean'],
            'cancellation_policy' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cancellation_window_hours' => ['sometimes', 'integer', 'min:0', 'max:720'],
        ];
    }
}
