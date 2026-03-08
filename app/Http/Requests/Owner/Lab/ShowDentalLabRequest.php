<?php

namespace App\Http\Requests\Owner\Lab;

use Illuminate\Foundation\Http\FormRequest;

class ShowDentalLabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'include' => ['nullable', 'string'],
        ];
    }
}
