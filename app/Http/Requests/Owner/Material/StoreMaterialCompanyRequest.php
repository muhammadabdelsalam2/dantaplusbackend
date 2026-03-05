<?php

namespace App\Http\Requests\Owner\Material;

use App\Models\MaterialCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:material_companies,email'],
            'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['required', 'string', Rule::in(config('material_market.company_category_keys', []))],
            'status' => ['nullable', Rule::in([MaterialCompany::STATUS_ACTIVE, MaterialCompany::STATUS_INACTIVE])],
            'is_featured' => ['nullable', 'boolean'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
