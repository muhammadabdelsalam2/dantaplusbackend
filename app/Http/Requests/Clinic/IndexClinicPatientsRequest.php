<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class IndexClinicPatientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
          
            'search'   => ['nullable', 'string', 'max:255'],

            'gender'   => ['nullable', 'string', 'in:male,female'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
