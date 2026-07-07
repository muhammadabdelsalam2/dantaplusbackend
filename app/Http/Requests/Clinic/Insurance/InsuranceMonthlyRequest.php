<?php

namespace App\Http\Requests\Clinic\Insurance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InsuranceMonthlyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = auth()->user()?->clinic_id;

        return [
            'year' => ['nullable', 'integer', 'min:2000', 'max:' . ((int) now()->format('Y') + 1)],
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
            'year.integer' => 'The year must be a valid integer.',
            'year.min' => 'The year must be 2000 or later.',
            'year.max' => 'The year cannot be later than next year.',
            'insurance_company_id.exists' => 'The selected insurance company was not found for this clinic.',
        ];
    }
}

