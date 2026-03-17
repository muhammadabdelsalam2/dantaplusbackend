<?php

namespace App\Http\Requests\Lab\Material;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLabMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:150'],
            'supplier' => ['sometimes', 'string', 'min:2', 'max:150'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'cost' => ['sometimes', 'numeric', 'min:0'],
            'purchase_date' => ['sometimes', 'date'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
        ];
    }
}
