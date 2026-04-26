<?php

namespace App\Http\Requests\Clinic\Insurance;

use App\Models\Clinic\Insurance\InsuranceClaim;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInsuranceClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'insurance_company_id' => ['required', 'integer', 'exists:insurance_companies,id'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:clinic_appointments,id'],
            'clinic_invoice_id' => ['nullable', 'integer', 'exists:clinic_invoices,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'service_date' => ['required', 'date'],
            'coverage_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'gross_amount' => ['required', 'numeric', 'min:0'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(InsuranceClaim::statuses())],
            'notes' => ['nullable', 'string'],
            'status_notes' => ['nullable', 'string'],
        ];
    }
}
