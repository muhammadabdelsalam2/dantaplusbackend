<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;

class TestQueueNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'appointment_id' => ['nullable', 'integer', 'exists:clinic_appointments,id'],
            'number_before' => ['nullable', 'integer', 'min:1', 'max:20'],
            'patient_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
