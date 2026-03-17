<?php

namespace App\Http\Requests\Lab\Settings;

use App\Enums\LabRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $labId = auth()->user()?->lab_id;

        return [
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->where(fn ($q) => $q->where('lab_id', $labId)),
            ],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(LabRole::class)],
            'commission_rates' => ['required_if:role,' . LabRole::LabTechnician->value, 'array'],
            'commission_rates.*' => ['numeric', 'min:0', 'max:100'],
        ];
    }
}
