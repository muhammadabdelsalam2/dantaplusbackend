<?php

namespace App\Http\Requests\Lab;

use App\Models\CaseModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('status')) {
            $this->merge(['status' => CaseModel::STATUS_PENDING]);
        }

        if (! $this->has('priority')) {
            $this->merge(['priority' => CaseModel::PRIORITY_MEDIUM]);
        }
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['required', 'integer', 'exists:clinics,id'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'dentist_id' => ['required', 'integer', 'exists:doctors,id'],
            'due_date' => ['required', 'date'],
            'case_type' => ['required', 'string', 'max:255'],
            'tooth_numbers' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(CaseModel::STATUSES)],
            'priority' => ['required', Rule::in(CaseModel::PRIORITIES)],
            'assigned_technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_delivery_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
