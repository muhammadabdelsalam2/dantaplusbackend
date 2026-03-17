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
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'clinic_type' => ['nullable', Rule::enum(ClinicType::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
