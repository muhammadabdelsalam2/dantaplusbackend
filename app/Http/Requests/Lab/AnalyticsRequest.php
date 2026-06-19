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
            'caseType' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],

            'clinicId' => [
                'nullable',
                Rule::when(
                    $this->input('clinicId') !== 'all',
                    ['integer', 'exists:clinics,id'],
                    ['in:all']
                ),
            ],
            'dentistId' => [
                'nullable',
                Rule::when(
                    $this->input('dentistId') !== 'all',
                    ['integer', 'exists:doctors,id'],
                    ['in:all']
                ),
            ],
            'status' => ['nullable', Rule::in(CaseModel::STATUSES)],
        ];
    }
}
