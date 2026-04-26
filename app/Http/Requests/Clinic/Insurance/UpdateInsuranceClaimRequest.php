<?php

namespace App\Http\Requests\Clinic\Insurance;

use App\Models\Clinic\Insurance\InsuranceClaim;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInsuranceClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'insurance_company_id' => ['sometimes', 'integer', 'exists:insurance_companies,id'],
            'patient_id' => ['sometimes', 'integer', 'exists:patients,id'],
            'appointment_id' => ['sometimes', 'nullable', 'integer', 'exists:clinic_appointments,id'],
            'clinic_invoice_id' => ['sometimes', 'nullable', 'integer', 'exists:clinic_invoices,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'service_date' => ['sometimes', 'date'],
            'coverage_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'gross_amount' => ['sometimes', 'numeric', 'min:0'],
            'approved_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'paid_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(InsuranceClaim::statuses())],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status_notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
