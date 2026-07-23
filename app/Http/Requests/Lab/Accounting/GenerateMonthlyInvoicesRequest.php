<?php

namespace App\Http\Requests\Lab\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateMonthlyInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'group_by' => ['nullable', Rule::in(['clinic', 'doctor'])],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'clinic_ids' => ['nullable', 'array'],
            'clinic_ids.*' => ['integer', 'exists:clinics,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'doctor_ids' => ['nullable', 'array'],
            'doctor_ids.*' => ['integer', 'exists:doctors,id'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
