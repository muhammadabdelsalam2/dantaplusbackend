<?php

namespace App\Http\Requests\Lab;

use App\Models\CaseModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'caseType' => ['nullable', 'string', 'max:120'],
            'case_type' => ['nullable', 'string', 'max:120'],
            'case_type_id' => ['nullable'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort_by' => ['nullable', 'string', 'max:80'],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],

            'clinicId' => [
                'nullable',
                Rule::when(
                    $this->input('clinicId') !== 'all',
                    ['integer', 'exists:clinics,id'],
                    ['in:all']
                ),
            ],
            'clinic_id' => ['nullable'],
            'dentistId' => [
                'nullable',
                Rule::when(
                    $this->input('dentistId') !== 'all',
                    ['integer', 'exists:doctors,id'],
                    ['in:all']
                ),
            ],
            'doctor_id' => ['nullable'],
            'status' => ['nullable', Rule::in(CaseModel::STATUSES)],
        ];
    }
}
