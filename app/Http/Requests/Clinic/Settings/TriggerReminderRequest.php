<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TriggerReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'appointment_ids' => ['nullable', 'array'],
            'appointment_ids.*' => ['integer', 'exists:clinic_appointments,id'],
            'patient_ids' => ['nullable', 'array'],
            'patient_ids.*' => ['integer', 'exists:patients,id'],
            'channel' => ['nullable', 'string', Rule::in(['whatsapp', 'sms', 'email'])],
            'template' => ['nullable', 'string', 'max:2000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
