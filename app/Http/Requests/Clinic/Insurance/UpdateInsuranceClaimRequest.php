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

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'clinic_invoice_id' => $this->input('clinic_invoice_id', $this->input('invoice_id')),
            'title' => $this->input('title', $this->input('service', $this->input('procedure'))),
            'service_date' => $this->input('service_date', $this->input('submission_date')),
            'gross_amount' => $this->input('gross_amount', $this->input('claim_amount', $this->input('amount'))),
        ], static fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        return [
            'insurance_company_id' => ['sometimes', 'integer', 'exists:insurance_companies,id'],
            'patient_id' => ['sometimes', 'integer', 'exists:patients,id'],
            'appointment_id' => ['sometimes', 'nullable', 'integer', 'exists:clinic_appointments,id'],
            'invoice_id' => ['sometimes', 'nullable', 'integer', 'exists:clinic_invoices,id'],
            'clinic_invoice_id' => ['sometimes', 'nullable', 'integer', 'exists:clinic_invoices,id'],
            'dentist_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'service' => ['sometimes', 'nullable', 'string', 'max:255'],
            'procedure' => ['sometimes', 'nullable', 'string', 'max:255'],
            'claim_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'submission_date' => ['sometimes', 'nullable', 'date'],
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
            'patient_consent_required' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array'],
            'items.*.insurance_price_list_item_id' => ['nullable', 'integer'],
            'items.*.service_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.service_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
