<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClinicInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = (int) auth()->user()?->clinic_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clinics', 'email')->ignore($clinicId)],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'clinic_type' => ['nullable', 'string', 'max:50'],
            'subdomain' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'subscription_plan' => ['nullable', 'in:Basic,Standard,Premium'],
            'payment_method' => ['nullable', 'in:Stripe,PayPal,Manual'],
            'status' => ['nullable', 'in:Active,Trial,Expired,Suspended'],
            'start_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'max_users' => ['nullable', 'integer', 'min:0'],
            'max_branches' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
