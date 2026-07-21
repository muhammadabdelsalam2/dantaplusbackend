<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class StoreExternalOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'clinic_id' => 'required|exists:clinics,id',
            'external_clinic_name' => 'required_without:clinic_id|required|string|max:255',
            'external_clinic_phone' => 'required_without:clinic_id|required|string|max:50',
            'notes' => 'nullable|string',
            'payment_method' => 'required|in:Cash,Visa',
            'delivery_address' => 'required|string|max:1000',
            'delivery_at' => 'required|date',
            'shipping_cost' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:material_products,id',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }
}
