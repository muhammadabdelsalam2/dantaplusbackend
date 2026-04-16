<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'product_id' => 'nullable|exists:material_products,id',
            'product_name' => 'required|string|max:255',
            'category_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'quantity' => 'required|integer|min:0',
            'minimum_stock_level' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'supplier' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive,low_stock',
        ];
    }
}
