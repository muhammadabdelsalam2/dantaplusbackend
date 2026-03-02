<?php

namespace App\Http\Requests\Owner\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class IndexClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:Active,Trial,Expired,Suspended'],
            'subscription_plan' => ['nullable', 'in:Basic,Standard,Premium'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'include' => ['nullable', 'string'],
        ];
    }
}
