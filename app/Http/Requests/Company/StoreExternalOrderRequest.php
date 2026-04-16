<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class StoreExternalOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'external_clinic_name' => 'required|string|max:255',
            'external_clinic_phone' => 'required|string|max:50',
            'notes' => 'nullable|string',
            'payment_method' => 'nullable|string|max:100',
            'payment_status' => 'nullable|string|max:50',
            'delivery_address' => 'nullable|string|max:1000',
            'delivery_at' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:material_products,id',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }
}
