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
            'service' => ['required', 'string', 'max:255'],
            'material' => ['required', 'string', 'max:255'],
            'shade' => ['required', 'string', 'max:50'],
            'delivery_date' => ['required', 'date'],
            'file_upload' => ['nullable', 'file', 'max:10240'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
