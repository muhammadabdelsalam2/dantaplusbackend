<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class StoreExternalOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'delivery_at' => $this->input('delivery_at', $this->input('expected_delivery')),
            'items' => $this->input('items', $this->input('materials')),
        ], static fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        return [
            'clinic_id' => 'nullable|exists:clinics,id|required_without:external_clinic_name',
            'external_clinic_name' => 'nullable|string|max:255|required_without:clinic_id',
            'external_clinic_phone' => 'nullable|string|max:50|required_without:clinic_id',
            'notes' => 'nullable|string',
            'payment_method' => 'required|in:Cash,Visa',
            'delivery_address' => 'required|string|max:1000',
            'delivery_at' => 'required|date',
            'shipping_cost' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:material_products,id',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.unit' => 'required|string|max:50',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ];
    }
}
