<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
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
            'notes' => 'sometimes|nullable|string',
            'payment_method' => 'sometimes|nullable|string|max:100',
            'payment_status' => 'sometimes|nullable|string|max:50',
            'delivery_address' => 'sometimes|nullable|string|max:1000',
            'delivery_at' => 'sometimes|nullable|date',
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:material_products,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ];
    }
}
