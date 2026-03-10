<?php

namespace App\Http\Requests\Owner\Material;

use App\Models\MaterialProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_id' => $this->route('company'),
            'status' => $this->has('status') ? strtolower($this->status) : null,
            'category' => $this->has('category') ? strtolower($this->category) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:material_companies,id'],
            'image' => ['required', 'image', 'max:5120'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', Rule::in(config('material_market.product_category_keys', []))],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in([
                MaterialProduct::STATUS_ACTIVE,
                MaterialProduct::STATUS_INACTIVE
            ])],
        ];
    }
}
