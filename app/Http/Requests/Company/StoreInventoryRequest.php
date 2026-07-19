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
            'clinic_id' => 'nullable|exists:clinics,id',
            'category_id' => 'nullable|exists:material_categories,id',
            'barcode' => 'nullable|string|max:255',
            'product_name' => 'required|string|max:255',
            'category_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'quantity' => 'required|integer|min:0',
            'minimum_stock_level' => 'required|integer|min:0',
            'reorder_quantity' => 'nullable|integer|min:0',
            'unit' => 'required|string|max:50',
            'consumption_per_case' => 'nullable|numeric|min:0',
            'auto_purchase' => 'nullable|boolean',
            'supplier' => 'nullable|string|max:255',
            'unit_price' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive,low_stock',
        ];
    }
}
