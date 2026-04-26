<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'patient_name' => ['required_without:patient_id', 'nullable', 'string', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:50'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_name' => ['required', 'string', 'max:255'],
            'appointment_at' => ['required', 'date'],
            'duration' => ['nullable', 'integer', 'min:5', 'max:480'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'branch' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'payment_type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:scheduled,confirmed,arrived,attended,completed,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
