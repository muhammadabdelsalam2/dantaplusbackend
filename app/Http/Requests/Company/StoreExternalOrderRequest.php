<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class StoreExternalOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items', $this->input('materials'));

        if (is_array($items)) {
            $items = collect($items)->map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                if (! isset($item['product_id'])) {
                    $item['product_id'] = $item['item_id'] ?? $item['material_product_id'] ?? null;
                }

                return $item;
            })->all();
        }

        $this->merge(array_filter([
            'delivery_at' => $this->input('delivery_at', $this->input('expected_delivery')),
            'items' => $items,
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
            'items.*.product_id' => 'required|exists:material_products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }
}
