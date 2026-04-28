<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClinicBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['paid', 'partial', 'overdue', 'pending'])],
            'patient_id' => ['nullable', 'integer'],
            'doctor_id' => ['nullable', 'integer'],
            'invoice_id' => ['nullable', 'integer'],
            'expense_category_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
