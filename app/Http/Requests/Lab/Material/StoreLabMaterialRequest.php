<?php

namespace App\Http\Requests\Lab\Material;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'supplier' => ['required', 'string', 'min:2', 'max:150'],
            'stock' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'cost' => ['required', 'numeric', 'min:0'],
            'purchase_date' => ['required', 'date'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
        ];
    }
}
