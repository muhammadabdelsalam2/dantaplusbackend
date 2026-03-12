<?php

namespace App\Http\Requests\Owner\Lab;

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
            'email' => ['nullable', 'email', 'max:255'],
            'working_hours' => ['nullable', 'string', 'max:255'],
            'avg_delivery_days' => ['required', 'numeric', 'min:0'],
            'response_speed' => ['nullable', Rule::in([
                DentalLab::RESPONSE_SPEED_FAST,
                DentalLab::RESPONSE_SPEED_MEDIUM,
                DentalLab::RESPONSE_SPEED_SLOW,
            ])],
            'status' => ['sometimes', Rule::in([
                DentalLab::STATUS_ACTIVE,
                DentalLab::STATUS_INACTIVE,
            ])],
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

            // Optional lab login account provisioning
            'admin_name' => ['nullable', 'string', 'max:255'],
            'admin_email' => [
                'nullable',
                'email',
                'max:255',
                'required_with:admin_password',
                Rule::unique('users', 'email'),
            ],
            'admin_password' => ['nullable', 'string', 'min:8', 'max:255', 'required_with:admin_email'],
            'admin_is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function passedValidation(): void
    {
        if ($this->filled('admin_email') && !$this->has('admin_is_active')) {
            $this->merge([
                'admin_is_active' => 1,
            ]);
        } elseif ($this->has('admin_is_active')) {
            $this->merge([
                'admin_is_active' => $this->boolean('admin_is_active') ? 1 : 0,
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'admin_email.required_with' => 'Admin email is required when admin password is provided.',
            'admin_password.required_with' => 'Admin password is required when admin email is provided.',
            'admin_email.unique' => 'This admin email is already used by another user.',
        ];
    }
}
