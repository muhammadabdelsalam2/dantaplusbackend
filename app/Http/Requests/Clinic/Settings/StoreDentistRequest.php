<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDentistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', Rule::unique('users', 'phone')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'insurance_commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cash_commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
