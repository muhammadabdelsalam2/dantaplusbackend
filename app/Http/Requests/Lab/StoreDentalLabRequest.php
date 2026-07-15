<?php

namespace App\Http\Requests\Lab;

use App\Models\DentalLab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDentalLabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        if ($this->has('is_external')) {
            $merged['is_external'] = filter_var($this->input('is_external'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($this->has('admin_is_active')) {
            $merged['admin_is_active'] = filter_var($this->input('admin_is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        if (!empty($merged)) {
            $this->merge($merged);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],

            // نفس الإيميل هيستخدم للـ Lab والـ Admin
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('dental_labs', 'email'),
                Rule::unique('users', 'email'),
            ],

            'working_hours' => ['nullable', 'string', 'max:255'],
            'avg_delivery_days' => ['required', 'numeric', 'min:0'],

            'response_speed' => [
                'nullable',
                Rule::in([
                    DentalLab::RESPONSE_SPEED_FAST,
                    DentalLab::RESPONSE_SPEED_MEDIUM,
                    DentalLab::RESPONSE_SPEED_SLOW,
                ]),
            ],

            'status' => [
                'sometimes',
                Rule::in([
                    DentalLab::STATUS_ACTIVE,
                    DentalLab::STATUS_INACTIVE,
                ]),
            ],

            'logo' => ['nullable', 'file', 'image', 'max:5120'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'is_external' => ['sometimes', 'boolean'],
            'date_added' => ['nullable', 'date'],
            'on_time_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rejection_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'services' => ['sometimes', 'array'],
            'services.*.name' => ['required_with:services', 'string', 'max:255'],
            'services.*.price' => ['nullable', 'numeric', 'min:0'],
            'services.*.turnaround_days' => ['nullable', 'numeric', 'min:0'],

            // إنشاء حساب الأدمن باستخدام نفس Email الخاص بالـ Lab
            'admin_name' => ['nullable', 'string', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'max:255'],
            'admin_is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function passedValidation(): void
    {
        if (!$this->has('admin_is_active')) {
            $this->merge([
                'admin_is_active' => 1,
            ]);
        } else {
            $this->merge([
                'admin_is_active' => $this->boolean('admin_is_active') ? 1 : 0,
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already used.',
            'admin_password.required' => 'Admin password is required.',
        ];
    }
}
