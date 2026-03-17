<?php

namespace App\Http\Requests\Lab\Material;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexLabMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'low_stock' => ['nullable', 'boolean'],
            'expiring_before' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'purchase_date_from' => ['nullable', 'date'],
            'purchase_date_to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', Rule::in([
                'id',
                'name',
                'supplier',
                'stock',
                'low_stock_threshold',
                'cost',
                'purchase_date',
                'expiration_date',
                'created_at',
            ])],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
