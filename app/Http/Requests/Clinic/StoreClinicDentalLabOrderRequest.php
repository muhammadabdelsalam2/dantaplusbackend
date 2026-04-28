<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicDentalLabOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dental_lab_id' => ['required', 'integer', 'exists:dental_labs,id'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'lab_service_id' => ['nullable', 'integer', 'exists:lab_services,id'],
            'status' => ['nullable', Rule::in(['pending', 'accepted', 'delivered'])],
            'due_date' => ['required', 'date'],
            'case_type' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'dental_lab_id' => $this->input('dental_lab_id', $this->input('provider_id')),
            'lab_service_id' => $this->input('lab_service_id', $this->input('provider_service_id')),
        ]);
    }
}
