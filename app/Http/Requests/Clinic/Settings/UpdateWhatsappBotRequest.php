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

        $aliases = [];
        if ($this->has('enabled') && ! $this->has('is_enabled')) {
            $aliases['is_enabled'] = $this->input('enabled');
        }
        if ($this->has('bot_working_hours.start_time') && ! $this->has('start_time')) {
            $aliases['start_time'] = $this->input('bot_working_hours.start_time');
        }
        if ($this->has('bot_working_hours.end_time') && ! $this->has('end_time')) {
            $aliases['end_time'] = $this->input('bot_working_hours.end_time');
        }
        if ($this->has('allowed_services_for_bot_booking') && ! $this->has('allowed_services')) {
            $aliases['allowed_services'] = $this->input('allowed_services_for_bot_booking');
        }
        if ($this->has('payment_requirement') && ! $this->has('require_deposit')) {
            $aliases['require_deposit'] = $this->input('payment_requirement') === 'Require Deposit';
        }
        if ($this->has('language_preference') && ! $this->has('language')) {
            $aliases['language'] = match ($this->input('language_preference')) {
                'Arabic' => 'ar',
                'English' => 'en',
                default => 'auto',
            };
        }
        if ($aliases !== []) {
            $this->merge($aliases);
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
            'enabled' => ['sometimes', 'boolean'],
            'bot_working_hours' => ['sometimes', 'array'],
            'bot_working_hours.start_time' => ['nullable', 'date_format:H:i'],
            'bot_working_hours.end_time' => ['nullable', 'date_format:H:i'],
            'language_preference' => ['sometimes', Rule::in(['Auto-detect (English/Arabic)', 'English', 'Arabic'])],
            'payment_requirement' => ['sometimes', Rule::in(['Require Deposit', 'None'])],
            'allowed_services_for_bot_booking' => ['sometimes', 'array'],
            'allowed_services_for_bot_booking.*' => ['string', 'max:255'],
        ];
    }
}
