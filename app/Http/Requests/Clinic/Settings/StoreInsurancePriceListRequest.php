<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreInsurancePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (is_string($items)) {
            $decoded = json_decode($items, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['items' => $decoded]);
            }
        }

        $normalizedItems = collect($this->input('items', []))
            ->map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                if (array_key_exists('item_code', $item) && ! array_key_exists('code', $item)) {
                    $item['code'] = $item['item_code'];
                }

                if (array_key_exists('category', $item) && ! array_key_exists('category_name', $item)) {
                    $item['category_name'] = $item['category'];
                }

                return $item;
            })
            ->all();

        if ($normalizedItems !== []) {
            $this->merge(['items' => $normalizedItems]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'items.*.code' => ['nullable', 'string', 'max:100'],
            'items.*.item_code' => ['nullable', 'string', 'max:100'],
            'items.*.service_name' => ['required_without:items.*.service_id', 'nullable', 'string', 'max:255'],
            'items.*.category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'items.*.category_name' => ['nullable', 'string', 'max:255'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
