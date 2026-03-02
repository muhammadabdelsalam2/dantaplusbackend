<?php

namespace App\Http\Requests\Owner\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class ShowClinicRequest extends FormRequest
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
