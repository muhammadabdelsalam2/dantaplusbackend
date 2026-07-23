<?php

namespace App\Http\Requests\Lab\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexLabAccountingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['All Statuses', 'Paid', 'Pending', 'Overdue', 'Disputed', 'pending', 'partial', 'paid', 'overdue', 'cancelled', 'disputed'])],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'technician_id' => ['nullable'],
            'category_id' => ['nullable', 'integer', 'exists:lab_expense_categories,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'group_by' => ['nullable', Rule::in(['clinic', 'doctor'])],
            'period' => ['nullable', Rule::in(['all_time', 'this_month', 'this_week'])],
            'material_type' => ['nullable', Rule::in(['All Materials', 'Zirconia', 'E-Max', 'PFM', 'PMMA', 'all', 'all_materials', 'zirconia', 'e_max', 'pfm', 'pmma'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
