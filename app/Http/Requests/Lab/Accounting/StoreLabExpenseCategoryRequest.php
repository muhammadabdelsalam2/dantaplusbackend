<?php

namespace App\Http\Requests\Lab\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }
}
