<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ToggleWhatsappBotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_enabled')) {
            $this->merge([
                'is_enabled' => filter_var($this->input('is_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    ?? $this->input('is_enabled'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
        ];
    }
}
