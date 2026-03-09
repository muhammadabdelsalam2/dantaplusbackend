<?php

namespace App\Http\Requests\Owner\Alerts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRenewalReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_ids' => ['required', 'array', 'min:1'],
            'clinic_ids.*' => ['integer', 'exists:clinics,id'],
            'channel' => ['required', Rule::in(['email', 'whatsapp', 'system'])],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
