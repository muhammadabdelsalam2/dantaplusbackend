<?php

namespace App\Http\Requests\Owner\Lab;

use Illuminate\Foundation\Http\FormRequest;

class BulkDentalLabDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:dental_labs,id'],
        ];
    }
}
