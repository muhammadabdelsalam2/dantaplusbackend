<?php

namespace App\Http\Requests\Clinic;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $patient = Patient::query()->with('user:id')->find($this->route('id'));
        $userId = $patient?->user_id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['sometimes', 'string', 'max:50', Rule::unique('users', 'phone')->ignore($userId)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'medical_history' => ['sometimes', 'nullable', 'string'],
            'allergies' => ['sometimes', 'nullable', 'string'],
            'current_medication' => ['sometimes', 'nullable', 'string'],
            'insurance_company_id' => ['sometimes', 'nullable', 'integer', 'exists:insurance_companies,id'],
            'insurance_provider' => ['sometimes', 'nullable', 'string', 'max:255'],
            'insurance_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}

