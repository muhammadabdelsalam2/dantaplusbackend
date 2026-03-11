<?php

namespace App\Http\Requests\Owner\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class IndexNotificationLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'channel' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
