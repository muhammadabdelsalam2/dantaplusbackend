<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMyClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $modules = $this->input('modules');

        if (is_array($modules)) {
            $modules = array_values(array_filter(array_map(function ($module) {
                return is_string($module) ? strtolower(trim($module)) : $module;
            }, $modules)));

            $this->merge(['modules' => $modules]);
        }
    }

    public function rules(): array
    {
        $clinicId = (int) auth()->user()?->clinic_id;

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
