<?php

namespace App\Http\Requests\Owner\Feedback;

use Illuminate\Foundation\Http\FormRequest;

class IndexFeedbackReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
