<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'notes' => 'sometimes|nullable|string',
            'payment_method' => 'sometimes|nullable|string|max:100',
            'payment_status' => 'sometimes|nullable|string|max:50',
            'delivery_address' => 'sometimes|nullable|string|max:1000',
            'delivery_at' => 'sometimes|nullable|date',
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'nullable|exists:material_products,id',
            'items.*.item_name' => 'required_with:items|string|max:255',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
        ];
    }
}
