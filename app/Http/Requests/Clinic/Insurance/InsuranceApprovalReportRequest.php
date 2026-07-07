<?php

namespace App\Http\Requests\Clinic\Insurance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InsuranceApprovalReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = auth()->user()?->clinic_id;

        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'insurance_company_id' => [
                'nullable',
                'integer',
                Rule::exists('insurance_companies', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.date' => 'The start date must be a valid date.',
            'date_to.date' => 'The end date must be a valid date.',
            'date_to.after_or_equal' => 'The end date must be after or equal to the start date.',
            'insurance_company_id.exists' => 'The selected insurance company was not found for this clinic.',
        ];
    }
}

