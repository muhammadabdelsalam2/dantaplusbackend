<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSecuritySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('enable_2fa')) {
            $this->merge([
                'enable_2fa' => filter_var($this->input('enable_2fa'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    ?? $this->input('enable_2fa'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'enable_2fa' => ['required', 'boolean'],
            'backup_schedule' => ['required', 'string', Rule::in(['disabled', 'daily', 'weekly', 'monthly'])],
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
