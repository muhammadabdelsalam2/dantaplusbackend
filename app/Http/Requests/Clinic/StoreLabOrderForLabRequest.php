<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabOrderForLabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'delivery_date' => $this->input('delivery_date', $this->input('due_date')),
            'service' => $this->input('service', $this->input('service_name')),
            'file_upload' => $this->file('file_upload') ?: $this->file('file'),
        ]);
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'dentist_id' => ['nullable', 'integer'],
            'case_type_id' => ['nullable', 'integer'],
            'service' => ['required_without:case_type_id', 'nullable', 'string', 'max:255'],
            'material_id' => ['nullable', 'integer'],
            'material' => ['required_without:material_id', 'nullable', 'string', 'max:255'],
            'shade_id' => ['nullable', 'integer'],
            'shade' => ['required_without:shade_id', 'nullable', 'string', 'max:50'],
            'tooth_numbers' => ['nullable', 'array'],
            'tooth_numbers.*' => ['integer'],
            'delivery_date' => ['required', 'date'],
            'file_upload' => ['nullable', 'file', 'max:10240'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:10240'],
            'notes' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ];
    }
}
