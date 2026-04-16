<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class StoreShippingZoneRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'zone_name' => 'required|string|max:255',
            'shipping_cost' => 'required|numeric|min:0',
            'estimated_delivery_time' => 'nullable|string|max:255',
            'polygon_coordinates' => 'nullable|array',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }
}
