<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $daysOff = $this->input('days_off');

        if (is_string($daysOff)) {
            $decoded = json_decode($daysOff, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['days_off' => $decoded]);
            }
        }

        if ($this->has('online_booking_enabled')) {
            $this->merge([
                'online_booking_enabled' => filter_var($this->input('online_booking_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    ?? $this->input('online_booking_enabled'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'working_hours_from' => ['sometimes', 'date_format:H:i'],
            'working_hours_to' => ['sometimes', 'date_format:H:i'],
            'days_off' => ['sometimes', 'array'],
            'days_off.*' => ['string', Rule::in(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])],
            'currency' => ['sometimes', 'string', Rule::in(['USD', 'EGP', 'SAR'])],
            'date_format' => ['sometimes', 'string', Rule::in(['MM/DD/YYYY', 'DD/MM/YYYY', 'YYYY-MM-DD'])],
            'online_booking_enabled' => ['sometimes', 'boolean'],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
        ];
    }
}
