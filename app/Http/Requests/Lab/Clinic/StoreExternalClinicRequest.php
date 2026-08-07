<?php

namespace App\Http\Requests\Lab\Clinic;

use App\Enums\ClinicType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'owner_name'       => ['nullable', 'string', 'max:255'],
            'phone'            => ['required', 'string', 'max:50'],
            'email'            => ['nullable', 'email', 'max:255'],
            'address'          => ['nullable', 'string', 'max:500'],
            'clinic_type'      => ['nullable', Rule::enum(ClinicType::class)],
            'notes'            => ['nullable', 'string'],

            // Optional array of doctor names (at least 1 item if the array is provided).
            // Frontend can send: doctors: ["Dr. Ahmed", "Dr. Sara"]
            'doctors'          => ['nullable', 'array', 'min:1'],
            'doctors.*'        => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'doctors.min'     => 'At least one doctor name is required when the doctors field is provided.',
            'doctors.*.required' => 'Each doctor entry must be a non-empty name.',
            'doctors.*.string'   => 'Each doctor name must be a string.',
            'doctors.*.max'      => 'Each doctor name must not exceed 255 characters.',
        ];
    }
}
