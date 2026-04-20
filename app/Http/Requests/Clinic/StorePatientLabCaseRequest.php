<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientLabCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lab_id' => ['required', 'integer', 'exists:dental_labs,id'],
            'due_date' => ['required', 'date'],
            'case_type' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', 'in:Normal,Urgent'],
            'tooth_numbers' => ['nullable', 'array'],
            'tooth_numbers.*' => ['string', 'max:20'],
            'description' => ['nullable', 'string'],
        ];
    }
}
