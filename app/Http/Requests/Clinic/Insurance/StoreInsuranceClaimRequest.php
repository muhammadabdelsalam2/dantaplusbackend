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

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'clinic_invoice_id' => $this->input('clinic_invoice_id', $this->input('invoice_id')),
            'title' => $this->input('title', $this->input('service', $this->input('procedure'))),
            'service_date' => $this->input('service_date', $this->input('submission_date')),
            'gross_amount' => $this->input('gross_amount', $this->input('claim_amount', $this->input('amount'))),
            'coverage_percentage' => $this->input('coverage_percentage', 100),
        ], static fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        return [
            'insurance_company_id' => ['required', 'integer', 'exists:insurance_companies,id'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:clinic_appointments,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:clinic_invoices,id'],
            'clinic_invoice_id' => ['nullable', 'integer', 'exists:clinic_invoices,id'],
            'dentist_id' => ['nullable', 'integer', 'exists:users,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'service' => ['nullable', 'string', 'max:255'],
            'procedure' => ['nullable', 'string', 'max:255'],
            'claim_amount' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'submission_date' => ['nullable', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'service_date' => ['required', 'date'],
            'coverage_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'gross_amount' => ['nullable', 'numeric', 'min:0'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(InsuranceClaim::statuses())],
            'notes' => ['nullable', 'string'],
            'status_notes' => ['nullable', 'string'],
            'patient_consent_required' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.insurance_price_list_item_id' => ['nullable', 'integer'],
            'items.*.service_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.service_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
