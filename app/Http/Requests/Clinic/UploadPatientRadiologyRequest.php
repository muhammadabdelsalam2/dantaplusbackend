<?php

namespace App\Http\Requests\Clinic;

use App\Support\Clinic\RadiologyModalities;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadPatientRadiologyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modality' => ['required', Rule::in(RadiologyModalities::values())],
            'teeth' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'record_date' => ['nullable', 'date'],
            'linked_appointment_id' => ['nullable', 'integer', 'exists:clinic_appointments,id'],
            'linked_treatment_id' => ['nullable', 'integer', 'exists:clinic_treatments,id'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
            'file' => ['nullable', 'file', 'max:10240'],
            'before_image' => ['nullable', 'file', 'image', 'max:10240'],
            'after_image' => ['nullable', 'file', 'image', 'max:10240'],
        ];
    }
}
