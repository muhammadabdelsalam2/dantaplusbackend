<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcurementOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'material_id' => ['required', 'integer', 'exists:material_products,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:material_companies,id'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
