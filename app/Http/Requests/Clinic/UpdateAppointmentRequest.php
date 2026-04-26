<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['sometimes', 'nullable', 'integer', 'exists:patients,id'],
            'patient_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'patient_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'doctor_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'service_name' => ['sometimes', 'string', 'max:255'],
            'appointment_at' => ['sometimes', 'date'],
            'duration' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'duration_minutes' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'branch' => ['sometimes', 'nullable', 'string', 'max:255'],
            'room' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payment_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'in:scheduled,confirmed,arrived,attended,completed,cancelled'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
