<?php

namespace App\Http\Requests\Owner\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicRequest extends FormRequest
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

        $this->merge([
            'modules' => $modules,
        ]);
    }


    if ($this->has('subscription_plan')) {
        $this->merge(['subscription_plan' => ucfirst(strtolower(trim($this->input('subscription_plan'))))]);
    }

    if ($this->has('payment_method')) {
        $pm = strtolower(trim($this->input('payment_method')));
        $map = ['stripe'=>'Stripe', 'paypal'=>'PayPal', 'manual'=>'Manual'];
        $this->merge(['payment_method' => $map[$pm] ?? $this->input('payment_method')]);
    }
}
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:clinics,email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:1000'],
            'subscription_plan' => ['required', 'in:Basic,Standard,Premium'],
            'payment_method' => ['required', 'in:Stripe,PayPal,Manual'],
            'max_users' => ['required', 'integer', 'min:0'],
            'max_branches' => ['required', 'integer', 'min:0'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => [
                'required',
                'string',
                Rule::in(config('clinic_modules.keys')),
            ],
            'admin_password' => ['required', 'string', 'min:8'],
        ];
    }
    public function messages(): array
{
    return [
        'modules.required' => 'Modules is required.',
        'modules.array' => 'Modules must be an array.',
        'modules.*.in' => 'Invalid module selected.',
        'admin_password.min' => 'Admin password must be at least 8 characters.',
    ];
}
}
