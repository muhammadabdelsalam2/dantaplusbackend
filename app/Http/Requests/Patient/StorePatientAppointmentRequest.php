<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('patient') === true;
    }

    public function rules(): array
    {
        return [
            'doctor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_name' => ['required', 'string', 'max:255'],
            'appointment_at' => ['required', 'date', 'after:now'],
            'duration' => ['nullable', 'integer', 'min:5', 'max:480'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'branch' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'payment_type' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'complaint' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
