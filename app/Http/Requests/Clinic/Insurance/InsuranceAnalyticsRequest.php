<?php

namespace App\Http\Requests\Clinic\Insurance;

use App\Models\Clinic\Insurance\InsuranceClaim;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InsuranceAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = auth()->user()?->clinic_id;

        return [
            'date_range' => ['nullable', Rule::in(['Last 30 Days', 'Last 90 Days', 'Last Year', 'All'])],
            'insurance_company_id' => [
                'nullable',
                'integer',
                Rule::exists('insurance_companies', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'status' => ['nullable', Rule::in(InsuranceClaim::reportStatuses())],
        ];
    }
}
