<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWhatsappBotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_enabled', 'require_deposit', 'ai_enabled'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                        ?? $this->input($field),
                ]);
            }
        }

        foreach (['welcome_message', 'out_of_hours_message', 'start_time', 'end_time', 'deposit_amount'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['sometimes', 'boolean'],
            'welcome_message' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'out_of_hours_message' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'end_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'language' => ['sometimes', Rule::in(['ar', 'en', 'auto'])],
            'require_deposit' => ['sometimes', 'boolean'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'required_if:require_deposit,true'],
            'allowed_services' => ['sometimes', 'array'],
            'allowed_services.*' => ['string', 'max:255'],
            'ai_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
