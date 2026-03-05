<?php

namespace App\Http\Requests\SuperAdmin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBillingPlansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'basic' => ['sometimes', 'array'],
            'basic.name' => ['sometimes', 'string', 'max:100'],
            'basic.monthly_price' => ['sometimes', 'numeric', 'min:0'],
            'basic.yearly_price' => ['sometimes', 'numeric', 'min:0'],
            'basic.description' => ['sometimes', 'nullable', 'string', 'max:500'],

            'standard' => ['sometimes', 'array'],
            'standard.name' => ['sometimes', 'string', 'max:100'],
            'standard.monthly_price' => ['sometimes', 'numeric', 'min:0'],
            'standard.yearly_price' => ['sometimes', 'numeric', 'min:0'],
            'standard.description' => ['sometimes', 'nullable', 'string', 'max:500'],

            'premium' => ['sometimes', 'array'],
            'premium.name' => ['sometimes', 'string', 'max:100'],
            'premium.monthly_price' => ['sometimes', 'numeric', 'min:0'],
            'premium.yearly_price' => ['sometimes', 'numeric', 'min:0'],
            'premium.description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
