<?php

namespace App\Http\Requests\Clinic\Insurance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInsuranceCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'coverage' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payment_terms' => ['sometimes', 'nullable', 'string', 'max:255'],
            'syndicate_price_list_id' => ['sometimes', 'nullable', 'integer', 'exists:insurance_price_lists,id'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
