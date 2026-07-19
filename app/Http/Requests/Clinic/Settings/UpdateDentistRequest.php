<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Validation\Rule;

class UpdateDentistRequest extends StoreDentistRequest
{
    public function rules(): array
    {
        $userId = (int) $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'specialization' => ['sometimes', 'nullable', 'string', 'max:255'],
            'branch_id' => ['sometimes', 'nullable', 'integer', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('clinic_id', $this->user()?->clinic_id))],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('users', 'phone')->ignore($userId)],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'insurance_commission' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'cash_commission' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'working_hours_from' => ['sometimes', 'nullable', 'date_format:H:i'],
            'working_hours_to' => ['sometimes', 'nullable', 'date_format:H:i'],
        ];
    }
}
