<?php

namespace App\Http\Requests\Clinic\Insurance;

use Illuminate\Foundation\Http\FormRequest;

class StoreInsuranceCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'coverage' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'syndicate_price_list_id' => ['nullable', 'integer', 'exists:insurance_price_lists,id'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
