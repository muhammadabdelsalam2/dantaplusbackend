<?php

namespace App\Http\Requests\Clinic\Insurance;

use Illuminate\Foundation\Http\FormRequest;

class PatientLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'patient_number' => ['nullable', 'string', 'max:100'],
            'query' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
