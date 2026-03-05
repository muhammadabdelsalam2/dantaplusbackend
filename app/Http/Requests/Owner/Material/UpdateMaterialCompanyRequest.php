<?php

namespace App\Http\Requests\Owner\Material;

use App\Models\MaterialCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) $this->route('company');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('material_companies', 'email')->ignore($companyId)],
            'commission_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'country' => ['sometimes', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['required', 'string', Rule::in(config('material_market.company_category_keys', []))],
            'status' => ['sometimes', Rule::in([MaterialCompany::STATUS_ACTIVE, MaterialCompany::STATUS_INACTIVE])],
            'is_featured' => ['sometimes', 'boolean'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
