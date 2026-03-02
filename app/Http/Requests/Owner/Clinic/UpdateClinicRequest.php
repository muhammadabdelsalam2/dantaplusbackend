<?php

namespace App\Http\Requests\Owner\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    protected function prepareForValidation(): void
    {
        $modules = $this->input('modules');

        if (is_array($modules)) {
            $modules = array_values(array_filter(array_map(function ($m) {
                return is_string($m) ? strtolower(trim($m)) : $m;
            }, $modules)));

            $this->merge(['modules' => $modules]);
        }
    }
    public function rules(): array
    {
        $clinicId = (int) $this->route('clinic');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'owner_name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('clinics', 'email')->ignore($clinicId),
            ],
            'phone' => ['sometimes', 'string', 'max:50'],
            'address' => ['sometimes', 'string', 'max:1000'],
            'subscription_plan' => ['sometimes', 'in:Basic,Standard,Premium'],
            'payment_method' => ['sometimes', 'in:Stripe,PayPal,Manual'],
            'max_users' => ['sometimes', 'integer', 'min:0'],
            'max_branches' => ['sometimes', 'integer', 'min:0'],
            'modules' => ['sometimes', 'array', 'min:1'],
            'modules.*' => ['required', 'string', Rule::in(config('clinic_modules.keys'))],
            'status' => ['sometimes', 'in:Active,Trial,Expired,Suspended'],
            'start_date' => ['sometimes', 'date'],
            'expiry_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
        ];
    }
}
