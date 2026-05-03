<?php

namespace App\Http\Requests\Owner\Material;

use App\Models\MaterialProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMaterialProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(config('material_market.product_category_keys', []))],
            'status' => ['nullable', Rule::in([MaterialProduct::STATUS_ACTIVE, MaterialProduct::STATUS_INACTIVE])],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
