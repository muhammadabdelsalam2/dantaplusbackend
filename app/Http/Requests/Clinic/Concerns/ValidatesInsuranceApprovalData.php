<?php

namespace App\Http\Requests\Clinic\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesInsuranceApprovalData
{
    protected function insuranceApprovalRules(string $prefix = ''): array
    {
        $clinicId = auth()->user()?->clinic_id;
        $key = static fn (string $field): string => $prefix . $field;

        return [
            $key('insurance_company_id') => [
                'nullable',
                'integer',
                Rule::exists('insurance_companies', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            $key('policy_number') => ['nullable', 'string', 'max:255'],
            $key('authorization_code') => ['nullable', 'string', 'max:255'],
            $key('approval_number') => ['nullable', 'string', 'max:255'],
            $key('ref_id') => ['nullable', 'string', 'max:255'],
            $key('code') => ['nullable', 'string', 'max:255'],
            $key('approval_date') => ['nullable', 'date'],
            $key('date') => ['nullable', 'date'],
            $key('expiry_date') => ['nullable', 'date'],
            $key('coverage') => ['nullable', 'numeric', 'min:0', 'max:100'],
            $key('coverage_percent') => ['nullable', 'numeric', 'min:0', 'max:100'],
            $key('approved_amount') => ['nullable', 'numeric', 'min:0'],
            $key('used_amount') => ['nullable', 'numeric', 'min:0'],
            $key('status') => ['nullable', 'in:Pending,Approved,Rejected,pending,approved,rejected'],
            $key('notes') => ['nullable', 'string'],
            $key('documents') => ['nullable', 'array'],
            $key('documents.*') => ['nullable'],
            $key('attachment') => ['nullable', 'file', 'max:10240'],
            $key('services') => ['nullable', 'array'],
            $key('services.*.service_name') => ['nullable', 'string', 'max:255'],
            $key('services.*.name') => ['nullable', 'string', 'max:255'],
            $key('services.*.service_code') => ['nullable', 'string', 'max:255'],
            $key('services.*.code') => ['nullable', 'string', 'max:255'],
            $key('services.*.amount') => ['nullable', 'numeric', 'min:0'],
            $key('services.*.value') => ['nullable', 'numeric', 'min:0'],
            $key('services.*.co_pay') => ['nullable', 'numeric', 'min:0'],
            $key('services.*.copay') => ['nullable', 'numeric', 'min:0'],
            $key('services.*.tooth_number') => ['nullable', 'string', 'max:50'],
            $key('services.*.tooth') => ['nullable', 'string', 'max:50'],
        ];
    }
}
