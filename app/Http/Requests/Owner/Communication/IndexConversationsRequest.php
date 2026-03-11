<?php

namespace App\Http\Requests\Owner\Communication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexConversationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'clinic_id' => $this->input('clinic_id', $this->input('clinicId')),
            'lab_id' => $this->input('lab_id', $this->input('labId')),
        ]);
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['all', 'interventions', 'resolved'])],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'lab_id' => ['nullable', 'integer', 'exists:dental_labs,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
